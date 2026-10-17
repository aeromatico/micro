<?php namespace Aero\Medical\Tests;

use Aero\Medical\Classes\ClinicalItems;
use Aero\Medical\Classes\ConsultationService;
use Aero\Medical\Classes\FollowUpSheet;
use Aero\Medical\Classes\MedicalException;
use Aero\Medical\Classes\Ranges;
use Aero\Medical\Models\Analyte;
use Aero\Medical\Models\Cie10;
use Aero\Medical\Models\ClinicSettings;
use Aero\Medical\Models\Consultation;
use Aero\Medical\Models\Diagnosis;
use Aero\Medical\Models\DiagnosisCatalog;
use Aero\Medical\Models\LabResult;
use Aero\Medical\Models\Medication;
use Aero\Medical\Models\Patient;
use Aero\Medical\Models\Prescription;
use Aero\Medical\Models\PrescriptionItem;
use Aero\Medical\Models\Questionnaire;
use Aero\Medical\Models\QuestionnaireAnswer;
use Aero\Medical\Models\Range;
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
            ClinicSettings::create(['tenant_id' => $tenant, 'mode' => $mode]);
        }
        $p = new Patient(['tenant_id' => $tenant, 'name' => 'Paciente ' . $tenant, 'species' => $mode === 'animal' ? 'dog' : null]);
        $p->kind = $mode;
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
        foreach (['cie10', 'liname', 'diagnosis_catalog', 'diagnoses', 'medications', 'prescriptions', 'prescription_items', 'exam_catalog', 'orders', 'order_items',
                  'questionnaires', 'questionnaire_answers', 'analytes', 'lab_results', 'ranges'] as $t) {
            $this->assertTrue(\Schema::hasTable('aero_medical_' . $t), "Falta aero_medical_{$t}");
        }
    }

    // ---- CIE-10 ----

    public function testCie10ImportFromOfficialFileAndSearch(): void
    {
        $this->assertSame(0, \Artisan::call('medical:import-cie10'));

        $this->assertGreaterThan(12000, Cie10::count());
        $this->assertStringContainsString('sin mención de complicación', Cie10::where('code', 'E11.9')->value('title'), 'Las subdivisiones comunes (E10–E14) existen');
        $this->assertSame(4, (int) Cie10::where('code', 'K25.0')->value('level'));
        $this->assertSame('Cólera', Cie10::where('code', 'A00')->value('title'));
        $this->assertSame(['A00.0', 'A00.1', 'A00.9'], Cie10::where('parent', 'A00')->orderBy('code')->pluck('code')->all());
        $this->assertSame('A00.0', Cie10::search('a00.0')->first()->code, 'Búsqueda por código, sin distinguir mayúsculas');
        $this->assertContains('J45', Cie10::search('Asma')->pluck('code')->all(), 'Búsqueda por nombre');
        $this->assertCount(0, Cie10::search('x'), 'Menos de 2 caracteres no busca');

        $this->assertSame(0, \Artisan::call('medical:import-cie10'));
        $this->assertSame(1, Cie10::where('code', 'A00.0')->count(), 'La importación es idempotente');
    }

    public function testLinameImportRejectsUnexpectedFiles(): void
    {
        $f = tempnam(sys_get_temp_dir(), 'lin');
        file_put_contents($f, "nombre,forma\nParacetamol,Comprimido\n");
        $this->assertSame(1, \Artisan::call('medical:import-liname', ['file' => $f]));
        $this->assertSame(0, \Aero\Medical\Models\Liname::count());

        file_put_contents($f, "code,name,form,concentration,atc,restricted\nN02 01 01,Paracetamol,Comprimido,500 mg,N02BE01,\n");
        $this->assertSame(0, \Artisan::call('medical:import-liname', ['file' => $f]));
        $this->assertSame(1, \Aero\Medical\Models\Liname::count());
        unlink($f);
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

        $this->expectException(MedicalException::class);
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

        $this->expectException(MedicalException::class);
        $this->items()->addDiagnosis($c, ['source' => 'free', 'label' => 'Lumbalgia mecánica'], $this->user());
    }

    public function testAnimalModeRejectsCie10ButAcceptsOwnCatalogAndFreeText(): void
    {
        $this->cie();
        $c = $this->note(1, 'animal');
        $own = DiagnosisCatalog::create(['tenant_id' => 1, 'source' => 'custom', 'label' => 'Otitis externa canina']);

        try {
            $this->items()->addDiagnosis($c, ['source' => 'cie10', 'code' => 'E11'], $this->user());
            $this->fail('CIE-10 no aplica en animal');
        } catch (MedicalException $e) {
            $this->assertStringContainsString('no aplica', $e->getMessage());
        }

        $d = $this->items()->addDiagnosis($c, ['source' => 'custom', 'catalog_id' => $own->id], $this->user());
        $this->assertSame('Otitis externa canina', $d->label);
        $this->items()->addDiagnosis($c, ['source' => 'free', 'label' => 'Dermatitis'], $this->user());

        $res = $this->items()->searchDiagnoses(1, 'animal', 'otitis');
        $this->assertCount(0, $res['cie'], 'En animal la búsqueda no ofrece CIE-10');
        $this->assertCount(1, $res['custom']);
    }

    public function testAnotherTenantsCatalogIsNotUsable(): void
    {
        $other = DiagnosisCatalog::create(['tenant_id' => 2, 'source' => 'custom', 'label' => 'Ajeno']);
        $c = $this->note(1);

        $this->expectException(MedicalException::class);
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

        $f = DiagnosisCatalog::frequent(1, 'human');
        $this->assertSame('I10', $f->first()->code, 'El más usado va primero');
        $this->assertSame(2, (int) $f->first()->uses);

        DiagnosisCatalog::where('tenant_id', 1)->where('code', 'E11')->update(['pinned' => true]);
        $this->assertSame('E11', DiagnosisCatalog::frequent(1, 'human')->first()->code, 'Los fijados van antes');
        $this->assertCount(0, DiagnosisCatalog::frequent(2, 'human'), 'Los Frecuentes son por negocio');
    }

    // ---- Receta y órdenes ----

    public function testPrescriptionItemsAreEncryptedAndLockedWhenSigned(): void
    {
        $med = Medication::create(['tenant_id' => 1, 'name' => 'Amoxicilina', 'form' => 'Cápsula', 'concentration' => '500 mg']);
        $c = $this->note();
        $i = $this->items()->addPrescriptionItem($c, ['source' => 'custom', 'ref_id' => $med->id, 'dose' => '1 cápsula', 'frequency' => 'cada 8 h', 'duration' => '7 días'], $this->user());

        $this->assertSame('Amoxicilina 500 mg (Cápsula)', $i->medication, 'El nombre sale del catálogo');
        $this->assertStringNotContainsString('Amoxicilina', (string) \DB::table('aero_medical_prescription_items')->where('id', $i->id)->value('medication'));

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
            } catch (\ApplicationException | MedicalException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
    }

    public function testOrdersFromCatalogAndLocking(): void
    {
        \Aero\Medical\Models\ExamCatalog::ensureDefaults(1);
        $ex = \Aero\Medical\Models\ExamCatalog::where('tenant_id', 1)->where('name', 'like', 'Hemograma%')->first();
        $c = $this->note();

        $i = $this->items()->addOrderItem($c, ['catalog_id' => $ex->id, 'instructions' => 'En ayunas'], $this->user());
        $this->assertSame($ex->name, $i->exam_name);
        $this->items()->addOrderItem($c, ['exam_name' => 'Examen especial'], $this->user());

        $this->sign($c);
        $this->expectException(\ApplicationException::class);
        \Aero\Medical\Models\OrderItem::first()->delete();
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
        $this->assertSame(['Uno', 'Dos'], \Aero\Medical\Models\Order::with('items')->first()->items->pluck('exam_name')->all());
        $n = Consultation::with(['diagnoses', 'addenda', 'answers'])->find($c->id);
        $this->assertSame(['I10', 'E11'], $n->diagnoses->pluck('code')->all());
        $this->assertCount(0, $n->addenda);
    }

    public function testOnlyTheAuthorEditsClinicalItems(): void
    {
        $c = $this->note(1, 'human', 7);

        $this->expectException(MedicalException::class);
        $this->items()->addPrescriptionItem($c, ['medication' => 'X'], $this->user(8));
    }

    public function testSignatureHashCoversDiagnosesRxAndQuestionnaires(): void
    {
        $this->cie();
        $c = $this->note();
        $this->items()->addDiagnosis($c, ['source' => 'cie10', 'code' => 'I10'], $this->user());
        $this->items()->addPrescriptionItem($c, ['medication' => 'Enalapril', 'dose' => '10 mg'], $this->user());
        $signed = $this->sign($c);
        $this->assertTrue($signed->integrityOk());

        // Alguien con acceso a la BD cambia el diagnóstico saltándose el modelo.
        \DB::table('aero_medical_diagnoses')->where('consultation_id', $c->id)->update(['label' => 'Otra cosa']);
        $this->assertFalse(Consultation::find($c->id)->integrityOk(), 'Cambiar un diagnóstico firmado rompe la huella');
    }

    // ---- Cuestionarios ----

    protected function ipssLike(): Questionnaire
    {
        $opts = [['label' => 'Nunca', 'score' => 0], ['label' => 'A veces', 'score' => 1], ['label' => 'Siempre', 'score' => 2]];

        return Questionnaire::create([
            'tenant_id' => 1, 'name' => 'Cuestionario de prueba', 'is_active' => true,
            'questions' => [['text' => 'Pregunta uno', 'options' => $opts], ['text' => 'Pregunta dos', 'options' => $opts], ['text' => 'Pregunta tres', 'options' => $opts]],
            'ranges' => [['min' => 0, 'max' => 2, 'label' => 'Leve'], ['min' => 3, 'max' => 4, 'label' => 'Moderado'], ['min' => 5, 'max' => 6, 'label' => 'Severo']],
        ]);
    }

    public function testQuestionnaireScoreIsSummedByCodeWithInclusiveRanges(): void
    {
        $q = $this->ipssLike();
        $def = ['questions' => $q->questions, 'ranges' => $q->ranges];

        $r = Questionnaire::evaluate($def, ['q1' => 0, 'q2' => 1, 'q3' => 1]);
        $this->assertSame(2.0, $r['score']);
        $this->assertSame('Leve', $r['interpretation'], 'El límite superior del rango es inclusivo');

        $this->assertSame('Moderado', Questionnaire::evaluate($def, ['q1' => 2, 'q2' => 1, 'q3' => 0])['interpretation']);
        $this->assertSame('Severo', Questionnaire::evaluate($def, ['q1' => 2, 'q2' => 2, 'q3' => 2])['interpretation']);
    }

    public function testQuestionnaireRejectsMissingAnswersAndBadDefinitions(): void
    {
        $q = $this->ipssLike();
        try {
            Questionnaire::evaluate(['questions' => $q->questions, 'ranges' => $q->ranges], ['q1' => 0, 'q2' => 9, 'q3' => 1]);
            $this->fail('Una opción inexistente debe rechazarse');
        } catch (\ApplicationException $e) {
            $this->assertStringContainsString('Pregunta dos', $e->getMessage());
        }

        $this->expectException(\ApplicationException::class);
        Questionnaire::create(['tenant_id' => 1, 'name' => 'Mal', 'questions' => [['text' => 'Una', 'options' => [['label' => 'Solo', 'score' => 1]]]]]);
    }

    public function testQuestionnaireAnswersAreStoredEncryptedReplacedAndLocked(): void
    {
        $q = $this->ipssLike();
        $c = $this->note();

        $a = $this->items()->answerQuestionnaire($c, $q, ['q1' => 1, 'q2' => 1, 'q3' => 1], $this->user());
        $this->assertSame(3.0, (float) $a->score);
        $this->assertSame('Moderado', $a->interpretation);
        $this->assertStringNotContainsString('q1', (string) \DB::table('aero_medical_questionnaire_answers')->value('answers'));

        $this->items()->answerQuestionnaire($c, $q, ['q1' => 0, 'q2' => 0, 'q3' => 0], $this->user());
        $this->assertSame(1, QuestionnaireAnswer::where('consultation_id', $c->id)->count(), 'Responder de nuevo reemplaza');
        $this->assertSame('Leve', QuestionnaireAnswer::first()->interpretation);

        // Cambiar la definición después no altera lo respondido (tiene su foto).
        $q->ranges = [['min' => 0, 'max' => 10, 'label' => 'Otro']];
        $q->save();
        $this->assertSame('Leve', QuestionnaireAnswer::first()->interpretation);

        $this->sign($c);
        $this->expectException(\ApplicationException::class);
        QuestionnaireAnswer::first()->update(['score' => 99]);
    }

    public function testAnotherTenantsQuestionnaireIsNotUsable(): void
    {
        $q = $this->ipssLike();
        $c = $this->note(2);

        $this->expectException(MedicalException::class);
        $this->items()->answerQuestionnaire($c, $q, ['q1' => 0, 'q2' => 0, 'q3' => 0], $this->user());
    }

    // ---- Rangos ----

    public function testRangesFlagLowAndHighAndNothingWithoutRules(): void
    {
        $this->assertNull(Ranges::flag(1, 'human', null, 'heart_rate', 200), 'Sin rango no se marca nada');

        Range::create(['tenant_id' => 1, 'mode' => 'human', 'metric' => 'heart_rate', 'low' => 50, 'high' => 100]);
        Ranges::flush();

        $this->assertSame('high', Ranges::flag(1, 'human', null, 'heart_rate', 101));
        $this->assertSame('low', Ranges::flag(1, 'human', null, 'heart_rate', 49));
        $this->assertNull(Ranges::flag(1, 'human', null, 'heart_rate', 100), 'Los límites son del rango normal');
        $this->assertNull(Ranges::flag(1, 'human', null, 'heart_rate', 50));
        $this->assertNull(Ranges::flag(1, 'animal', 'dog', 'heart_rate', 200), 'Humano y animal son independientes');
        $this->assertNull(Ranges::flag(2, 'human', null, 'heart_rate', 200), 'Cada negocio tiene los suyos');
    }

    public function testSpeciesRangeWinsOverTheGeneralOne(): void
    {
        Range::create(['tenant_id' => 1, 'mode' => 'animal', 'metric' => 'heart_rate', 'low' => 60, 'high' => 140]);
        Range::create(['tenant_id' => 1, 'mode' => 'animal', 'species' => 'cat', 'metric' => 'heart_rate', 'low' => 140, 'high' => 220]);
        Ranges::flush();

        $this->assertNull(Ranges::flag(1, 'animal', 'cat', 'heart_rate', 180), 'Para gato rige su rango');
        $this->assertSame('high', Ranges::flag(1, 'animal', 'dog', 'heart_rate', 180), 'Para otra especie rige el general');
    }

    public function testRangeNeedsALimitAndSaneLimits(): void
    {
        try {
            Range::create(['tenant_id' => 1, 'mode' => 'human', 'metric' => 'spo2']);
            $this->fail('Sin límites no es un rango');
        } catch (\ApplicationException $e) {
            $this->assertStringContainsString('al menos un límite', $e->getMessage());
        }
        $this->expectException(\ApplicationException::class);
        Range::create(['tenant_id' => 1, 'mode' => 'human', 'metric' => 'spo2', 'low' => 100, 'high' => 90]);
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
            } catch (MedicalException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
    }

    public function testFollowUpSheetColumnsRowsFlagsAndDrafts(): void
    {
        Range::create(['tenant_id' => 1, 'mode' => 'human', 'metric' => 'systolic', 'low' => 90, 'high' => 130]);
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
        $this->assertSame(0, \Aero\Medical\Models\OrderItem::count());
    }
}
