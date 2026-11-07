<?php namespace Aero\Clinica\Tests;

use Aero\Clinica\Classes\ClinicalItems;
use Aero\Clinica\Classes\ConsultationService;
use Aero\Clinica\Classes\FollowUpSheet;
use Aero\Clinica\Classes\ClinicaException;
use Aero\Clinica\Classes\Ranges;
use Aero\Clinica\Models\Analyte;
use Aero\Clinica\Models\Cie10;
use Aero\Clinica\Models\ClinicSettings;
use Aero\Clinica\Models\Consultation;
use Aero\Clinica\Models\Diagnosis;
use Aero\Clinica\Models\DiagnosisCatalog;
use Aero\Clinica\Models\LabResult;
use Aero\Clinica\Models\Patient;
use Aero\Clinica\Models\Prescription;
use Aero\Clinica\Models\PrescriptionItem;
use Aero\Clinica\Models\Range;
use PluginTestCase;

class ClinicalTest extends PluginTestCase
{
    protected function user(int $id = 7): object
    {
        return (object) ['id' => $id, 'first_name' => 'Ana', 'last_name' => 'Médica', 'email' => "u{$id}@example.test"];
    }

    protected function items(): ClinicalItems
    {
        return new ClinicalItems();
    }

    protected function note(int $tenant = 1, string $mode = 'human', int $author = 7): Consultation
    {
        if (!ClinicSettings::where('tenant_id', $tenant)->exists()) {
            ClinicSettings::create(['tenant_id' => $tenant]);
        }
        $p = new Patient(['tenant_id' => $tenant, 'name' => 'Paciente ' . $tenant]);
        // Con Office instalado todo animal exige tutor; sus tablas se crean solo para la prueba.
        if ($mode === 'animal' && $this->officeTables()) {
            $p->customer_id = \Aero\Office\Models\Customer::create(['tenant_id' => $tenant, 'name' => 'T', 'phone' => '7000009' . $tenant])->id;
        }
        $p->save();

        return (new ConsultationService())->start($p, 'consultation', $this->user($author));
    }

    protected function officeTables(): bool
    {
        $file = base_path('plugins/aero/office/updates/create_office_tables.php');
        if (!is_file($file)) {
            return false;
        }
        if (!\Schema::hasTable('aero_office_customers')) {
            (require $file)->up();
        }

        return true;
    }

    protected function sign(Consultation $c, int $author = 7): Consultation
    {
        $svc = new ConsultationService();
        $svc->autosave($c, ['reason' => 'Motivo'], [], $this->user($author));

        return $svc->sign($c->fresh(), $this->user($author))['consultation']->fresh();
    }

    protected function cie(): void
    {
        Cie10::insert([
            ['code' => 'E11', 'level' => 3, 'parent' => null, 'chapter' => 4, 'title' => 'Diabetes mellitus no insulinodependiente'],
            ['code' => 'E11.9', 'level' => 4, 'parent' => 'E11', 'chapter' => 4, 'title' => 'Diabetes mellitus no insulinodependiente, sin mención de complicación'],
            ['code' => 'I10', 'level' => 3, 'parent' => null, 'chapter' => 9, 'title' => 'Hipertensión esencial (primaria)'],
        ]);
    }

    public function testMigrationsCreateTables(): void
    {
        foreach (['cie10', 'diagnosis_catalog', 'diagnoses', 'prescriptions', 'prescription_items', 'exam_catalog', 'orders', 'order_items',
                  'analytes', 'lab_results', 'ranges'] as $t) {
            $this->assertTrue(\Schema::hasTable('aero_clinica_' . $t), "Falta aero_clinica_{$t}");
        }
    }

    // ---- CIE-10 ----

    public function testCie10ImportFromOfficialFileAndSearch(): void
    {
        $this->assertSame(0, \Artisan::call('clinica:import-cie10'));

        $this->assertGreaterThan(12000, Cie10::count());
        $this->assertStringContainsString('sin mención de complicación', Cie10::where('code', 'E11.9')->value('title'), 'Las subdivisiones comunes (E10–E14) existen');
        $this->assertSame(4, (int) Cie10::where('code', 'K25.0')->value('level'));
        $this->assertSame('Cólera', Cie10::where('code', 'A00')->value('title'));
        $this->assertSame(['A00.0', 'A00.1', 'A00.9'], Cie10::where('parent', 'A00')->orderBy('code')->pluck('code')->all());
        $this->assertSame('A00.0', Cie10::search('a00.0')->first()->code, 'Búsqueda por código, sin distinguir mayúsculas');
        $this->assertContains('J45', Cie10::search('Asma')->pluck('code')->all(), 'Búsqueda por nombre');
        $this->assertCount(0, Cie10::search('x'), 'Menos de 2 caracteres no busca');

        $this->assertSame(0, \Artisan::call('clinica:import-cie10'));
        $this->assertSame(1, Cie10::where('code', 'A00.0')->count(), 'La importación es idempotente');
    }

    // ---- Diagnósticos ----

    public function testCie10DiagnosisTakesTheOfficialTitleNotTheClientsText(): void
    {
        $this->cie();
        $c = $this->note();
        $d = $this->items()->addDiagnosis($c, ['source' => 'cie10', 'code' => 'e11.9', 'label' => 'TEXTO FALSO'], $this->user());

        $this->assertSame('E11.9', $d->code);
        $this->assertStringStartsWith('Diabetes mellitus', $d->label);
        $this->assertTrue($d->is_primary, 'El primero es el principal');
        $this->assertSame('presumptive', $d->certainty);

        $this->expectException(ClinicaException::class);
        $this->items()->addDiagnosis($c, ['source' => 'cie10', 'code' => 'ZZZ'], $this->user());
    }

    public function testPrimaryIsUniqueAndReassignedOnRemoval(): void
    {
        $this->cie();
        $c = $this->note();
        $a = $this->items()->addDiagnosis($c, ['source' => 'cie10', 'code' => 'E11'], $this->user());
        $b = $this->items()->addDiagnosis($c, ['source' => 'cie10', 'code' => 'I10', 'primary' => true, 'certainty' => 'confirmed'], $this->user());

        $this->assertSame([$b->id], Diagnosis::where('consultation_id', $c->id)->where('is_primary', true)->pluck('id')->all());
        $this->assertSame('confirmed', $b->fresh()->certainty);

        $this->items()->removeDiagnosis($c, $b->fresh(), $this->user());
        $this->assertTrue($a->fresh()->is_primary, 'Al quitar el principal, el siguiente lo es');
    }

    public function testFreeTextAndDuplicates(): void
    {
        $c = $this->note();
        $this->items()->addDiagnosis($c, ['source' => 'free', 'label' => 'Lumbalgia mecánica'], $this->user());

        $this->expectException(ClinicaException::class);
        $this->items()->addDiagnosis($c, ['source' => 'free', 'label' => 'Lumbalgia mecánica'], $this->user());
    }

    public function testAnotherTenantsCatalogIsNotUsable(): void
    {
        $other = DiagnosisCatalog::create(['tenant_id' => 2, 'source' => 'custom', 'label' => 'Ajeno']);
        $c = $this->note(1);

        $this->expectException(ClinicaException::class);
        $this->items()->addDiagnosis($c, ['source' => 'custom', 'catalog_id' => $other->id], $this->user());
    }

    public function testFrequentsGrowWithSignedNotesAndPinnedGoFirst(): void
    {
        $this->cie();
        foreach ([1, 2] as $i) {
            $c = $this->note(1, 'human', 7);
            $this->items()->addDiagnosis($c, ['source' => 'cie10', 'code' => 'I10'], $this->user());
            if ($i === 1) {
                $this->items()->addDiagnosis($c, ['source' => 'cie10', 'code' => 'E11'], $this->user());
            }
            $this->sign($c);
        }

        $f = DiagnosisCatalog::frequent(1);
        $this->assertSame('I10', $f->first()->code, 'El más usado va primero');
        $this->assertSame(2, (int) $f->first()->uses);

        DiagnosisCatalog::where('tenant_id', 1)->where('code', 'E11')->update(['pinned' => true]);
        $this->assertSame('E11', DiagnosisCatalog::frequent(1)->first()->code, 'Los fijados van antes');
        $this->assertCount(0, DiagnosisCatalog::frequent(2), 'Los Frecuentes son por negocio');
    }

    // ---- Receta y órdenes ----

    public function testPrescriptionItemsAreEncryptedAndLockedWhenSigned(): void
    {
        $c = $this->note();
        $i = $this->items()->addPrescriptionItem($c, ['medication' => 'Amoxicilina 500 mg (Cápsula)', 'dose' => '1 cápsula', 'frequency' => 'cada 8 h', 'duration' => '7 días'], $this->user());

        $this->assertSame('Amoxicilina 500 mg (Cápsula)', $i->medication);
        $this->assertStringNotContainsString('Amoxicilina', (string) \DB::table('aero_clinica_prescription_items')->where('id', $i->id)->value('medication'));

        $this->items()->addPrescriptionItem($c, ['medication' => 'Suero oral', 'instructions' => 'Tomar en pequeños sorbos'], $this->user());
        $this->assertSame(2, PrescriptionItem::count());

        $signed = $this->sign($c);
        $this->assertTrue($signed->integrityOk());

        foreach ([
            fn () => PrescriptionItem::first()->update(['dose' => '10 cápsulas']),
            fn () => PrescriptionItem::first()->delete(),
            fn () => PrescriptionItem::create(['tenant_id' => 1, 'prescription_id' => Prescription::first()->id, 'medication' => 'Extra']),
            fn () => Prescription::first()->update(['notes' => 'x']),
            fn () => $this->items()->addPrescriptionItem($signed, ['medication' => 'Otro'], $this->user()),
        ] as $n => $attempt) {
            try {
                $attempt();
                $this->fail("El intento #{$n} sobre la receta firmada debió fallar");
            } catch (\ApplicationException | ClinicaException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
    }

    public function testOrdersFromCatalogAndLocking(): void
    {
        \Aero\Clinica\Models\ExamCatalog::ensureDefaults(1);
        $ex = \Aero\Clinica\Models\ExamCatalog::where('tenant_id', 1)->where('name', 'like', 'Hemograma%')->first();
        $c = $this->note();

        $i = $this->items()->addOrderItem($c, ['catalog_id' => $ex->id, 'instructions' => 'En ayunas'], $this->user());
        $this->assertSame($ex->name, $i->exam_name);
        $this->items()->addOrderItem($c, ['exam_name' => 'Examen especial'], $this->user());

        $this->sign($c);
        $this->expectException(\ApplicationException::class);
        \Aero\Clinica\Models\OrderItem::first()->delete();
    }

    public function testRelationsLoadInOrder(): void
    {
        $this->cie();
        $c = $this->note();
        $this->items()->addDiagnosis($c, ['source' => 'cie10', 'code' => 'I10'], $this->user());
        $this->items()->addDiagnosis($c, ['source' => 'cie10', 'code' => 'E11'], $this->user());
        $this->items()->addPrescriptionItem($c, ['medication' => 'Primero'], $this->user());
        $this->items()->addPrescriptionItem($c, ['medication' => 'Segundo'], $this->user());
        $this->items()->addOrderItem($c, ['exam_name' => 'Uno'], $this->user());
        $this->items()->addOrderItem($c, ['exam_name' => 'Dos'], $this->user());

        // Es lo que hacen las pantallas y la impresión: cargar las relaciones con with().
        $this->assertSame(['Primero', 'Segundo'], Prescription::with('items')->first()->items->pluck('medication')->all());
        $this->assertSame(['Uno', 'Dos'], \Aero\Clinica\Models\Order::with('items')->first()->items->pluck('exam_name')->all());
        $n = Consultation::with(['diagnoses', 'addenda'])->find($c->id);
        $this->assertSame(['I10', 'E11'], $n->diagnoses->pluck('code')->all());
        $this->assertCount(0, $n->addenda);
    }

    public function testOnlyTheAuthorEditsClinicalItems(): void
    {
        $c = $this->note(1, 'human', 7);

        $this->expectException(ClinicaException::class);
        $this->items()->addPrescriptionItem($c, ['medication' => 'X'], $this->user(8));
    }

    public function testSignatureHashCoversDiagnosesAndRx(): void
    {
        $this->cie();
        $c = $this->note();
        $this->items()->addDiagnosis($c, ['source' => 'cie10', 'code' => 'I10'], $this->user());
        $this->items()->addPrescriptionItem($c, ['medication' => 'Enalapril', 'dose' => '10 mg'], $this->user());
        $signed = $this->sign($c);
        $this->assertTrue($signed->integrityOk());

        // Alguien con acceso a la BD cambia el diagnóstico saltándose el modelo.
        \DB::table('aero_clinica_diagnoses')->where('consultation_id', $c->id)->update(['label' => 'Otra cosa']);
        $this->assertFalse(Consultation::find($c->id)->integrityOk(), 'Cambiar un diagnóstico firmado rompe la huella');
    }

    // ---- Rangos ----

    public function testRangesFlagLowAndHighAndNothingWithoutRules(): void
    {
        $this->assertNull(Ranges::flag(1, null, 'heart_rate', 200), 'Sin rango no se marca nada');

        Range::create(['tenant_id' => 1, 'metric' => 'heart_rate', 'low' => 50, 'high' => 100]);
        Ranges::flush();

        $this->assertSame('high', Ranges::flag(1, null, 'heart_rate', 101));
        $this->assertSame('low', Ranges::flag(1, null, 'heart_rate', 49));
        $this->assertNull(Ranges::flag(1, null, 'heart_rate', 100), 'Los límites son del rango normal');
        $this->assertNull(Ranges::flag(1, null, 'heart_rate', 50));
        $this->assertNull(Ranges::flag(2, null, 'heart_rate', 200), 'Cada negocio tiene los suyos');
    }

    public function testSexSpecificRangeWinsOverTheGeneralOne(): void
    {
        Range::create(['tenant_id' => 1, 'metric' => 'heart_rate', 'low' => 60, 'high' => 100]);
        Range::create(['tenant_id' => 1, 'sex' => 'female', 'metric' => 'heart_rate', 'low' => 70, 'high' => 110]);
        Ranges::flush();

        $this->assertNull(Ranges::flag(1, 'female', 'heart_rate', 105), 'Para mujeres rige su rango');
        $this->assertSame('high', Ranges::flag(1, 'male', 'heart_rate', 105), 'Para el otro sexo rige el general');
        $this->assertSame('high', Ranges::flag(1, null, 'heart_rate', 105), 'Sin sexo rige el general');
    }

    public function testRangeNeedsALimitAndSaneLimits(): void
    {
        try {
            Range::create(['tenant_id' => 1, 'metric' => 'spo2']);
            $this->fail('Sin límites no es un rango');
        } catch (\ApplicationException $e) {
            $this->assertStringContainsString('al menos un límite', $e->getMessage());
        }
        $this->expectException(\ApplicationException::class);
        Range::create(['tenant_id' => 1, 'metric' => 'spo2', 'low' => 100, 'high' => 90]);
    }

    // ---- Laboratorio y hoja de seguimiento ----

    public function testLabResultsValidation(): void
    {
        $p = $this->note()->patient;

        $r = $this->items()->addLab($p, ['analyte_name' => 'Glucosa en ayunas', 'value' => '105,5', 'unit' => 'mg/dL', 'measured_on' => now()->subDay()->toDateString()], $this->user());
        $this->assertEquals(105.5, $r->value);

        foreach ([
            ['analyte_name' => 'X', 'value' => 'abc'],
            ['analyte_name' => '', 'value' => '1'],
            ['analyte_name' => 'X', 'value' => '1', 'measured_on' => now()->addDay()->toDateString()],
        ] as $bad) {
            try {
                $this->items()->addLab($p, $bad, $this->user());
                $this->fail('Debió rechazar ' . json_encode($bad));
            } catch (ClinicaException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
    }

    public function testFollowUpSheetColumnsRowsFlagsAndDrafts(): void
    {
        Range::create(['tenant_id' => 1, 'metric' => 'systolic', 'low' => 90, 'high' => 130]);
        Ranges::flush();
        $this->cie();

        $svc = new ConsultationService();
        $first = $this->note();
        $p = $first->patient;
        $svc->autosave($first, ['reason' => 'Control 1'], ['systolic' => '150', 'diastolic' => '95', 'weight_kg' => '80', 'height_cm' => '170'], $this->user());
        $this->items()->addDiagnosis($first->fresh(), ['source' => 'cie10', 'code' => 'I10', 'certainty' => 'confirmed'], $this->user());
        $this->items()->addPrescriptionItem($first->fresh(), ['medication' => 'Enalapril', 'dose' => '10 mg'], $this->user());
        $svc->sign($first->fresh(), $this->user());

        $second = $svc->start($p, 'control', $this->user());
        $svc->autosave($second, ['reason' => 'Control 2'], ['systolic' => '120'], $this->user());
        $svc->sign($second->fresh(), $this->user());

        $draft = $svc->start($p, 'control', $this->user());
        $svc->autosave($draft, ['reason' => 'borrador'], ['systolic' => '200'], $this->user());

        $this->items()->addLab($p, ['analyte_name' => 'Glucosa en ayunas', 'value' => '99', 'unit' => 'mg/dL', 'measured_on' => now()->toDateString()], $this->user());
        $this->items()->addLab($p, ['analyte_name' => 'HbA1c', 'value' => '7', 'unit' => '%', 'measured_on' => now()->toDateString()], $this->user());

        $sheet = FollowUpSheet::build($p->fresh());
        $this->assertCount(3, $sheet['columns'], 'Una columna por visita (la de laboratorio cae en la fecha de hoy)');

        $rows = collect($sheet['rows'])->keyBy('label');
        $sys = $rows['PA sistólica']['cells'];
        $k1 = 'n' . $first->id;
        $k2 = 'n' . $second->id;
        $k3 = 'n' . $draft->id;

        $this->assertSame('high', $sys[$k1]['flag'], '150 está fuera del rango y va marcado');
        $this->assertNull($sys[$k2]['flag']);
        $this->assertArrayNotHasKey($k3, $sys, 'El borrador no aporta datos a la hoja');

        $this->assertArrayHasKey('IMC', $rows->all());
        $this->assertSame('27.7 kg/m²', $rows['IMC']['cells'][$k1]['text']);
        $this->assertArrayHasKey('Glucosa en ayunas', $rows->all(), 'El laboratorio anotado a mano entra en la hoja');
        $this->assertStringContainsString('I10', $rows['Diagnósticos']['cells'][$k1]['text']);
        $this->assertStringContainsString('Enalapril', $rows['Receta']['cells'][$k1]['text']);

        $signedRow = collect($sheet['columns'])->pluck('signed', 'key');
        $this->assertTrue($signedRow[$k1]);
        $this->assertFalse($signedRow[$k3]);
    }

    public function testLabOnlyDatesGetTheirOwnColumnAndSheetIsolatedByTenant(): void
    {
        $svc = new ConsultationService();
        $c = $this->note(1);
        $svc->autosave($c, ['reason' => 'x'], [], $this->user());
        $svc->sign($c->fresh(), $this->user());
        $this->items()->addLab($c->patient, ['analyte_name' => 'Glucosa en ayunas', 'value' => '90', 'measured_on' => now()->toDateString()], $this->user());

        $other = $this->note(2)->patient;
        $this->items()->addLab($other, ['analyte_name' => 'Glucosa en ayunas', 'value' => '300', 'measured_on' => now()->toDateString()], $this->user());

        $sheet = FollowUpSheet::build($c->patient->fresh());
        $labels = collect($sheet['rows'])->pluck('label')->all();
        $this->assertContains('Glucosa en ayunas', $labels);
        $cell = collect($sheet['rows'])->firstWhere('label', 'Glucosa en ayunas')['cells'];
        $this->assertSame(['90'], array_values(array_column($cell, 'text')), 'Solo los resultados de este paciente');
        $this->assertSame(1, LabResult::where('tenant_id', 1)->count());
    }

    public function testDiscardingADraftRemovesItsClinicalItems(): void
    {
        $this->cie();
        $c = $this->note();
        $this->items()->addDiagnosis($c, ['source' => 'cie10', 'code' => 'I10'], $this->user());
        $this->items()->addPrescriptionItem($c, ['medication' => 'X'], $this->user());
        $this->items()->addOrderItem($c, ['exam_name' => 'Y'], $this->user());

        (new ConsultationService())->discard($c->fresh(), $this->user());

        $this->assertSame(0, Diagnosis::count());
        $this->assertSame(0, PrescriptionItem::count());
        $this->assertSame(0, \Aero\Clinica\Models\OrderItem::count());
    }
}
