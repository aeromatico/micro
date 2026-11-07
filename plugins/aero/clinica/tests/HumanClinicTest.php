<?php namespace Aero\Clinica\Tests;

use Aero\Clinica\Classes\ClinicalItems;
use Aero\Clinica\Classes\ClinicaException;
use Aero\Clinica\Classes\ConsultationService;
use Aero\Clinica\Classes\DefaultDocuments;
use Aero\Clinica\Classes\DocumentRenderer;
use Aero\Clinica\Classes\VaccineService;
use Aero\Clinica\Models\ClinicSettings;
use Aero\Clinica\Models\DocumentTemplate;
use Aero\Clinica\Models\LabResult;
use Aero\Clinica\Models\Medication;
use Aero\Clinica\Models\OrderItem;
use Aero\Clinica\Models\Patient;
use Aero\Clinica\Models\PrescriptionItem;
use Aero\Clinica\Models\Vaccination;
use PluginTestCase;

/** Lo propio del producto de medicina humana: ficha ampliada, receta, laboratorio, vacunación y formatos médicos. */
class HumanClinicTest extends PluginTestCase
{
    protected function user(): object
    {
        return (object) ['id' => 7, 'first_name' => 'Ana', 'last_name' => 'Médica', 'email' => 'u7@example.test'];
    }

    protected function patient(int $tenant = 1, array $extra = []): Patient
    {
        ClinicSettings::firstOrCreate(['tenant_id' => $tenant]);

        return Patient::create($extra + ['tenant_id' => $tenant, 'name' => 'Luis Rojas']);
    }

    protected function note(Patient $p)
    {
        return (new ConsultationService())->start($p, 'consultation', $this->user());
    }

    public function testPatientFichaStoresPersonalEmergencyAndEncryptedClinicalData(): void
    {
        $p = $this->patient(1, [
            'blood_type' => 'O+', 'occupation' => 'Ingeniero', 'marital_status' => 'married', 'address' => 'Av. 6 de Agosto 123', 'city' => 'La Paz',
            'emergency_name' => 'Marta Rojas', 'emergency_phone' => '70000000', 'emergency_relation' => 'Esposa',
            'chronic_conditions' => 'Hipertensión arterial', 'habits' => 'No fuma', 'allergies' => 'Penicilina',
        ]);
        $p = Patient::find($p->id);

        $this->assertSame('Marta Rojas', $p->emergency_name);
        $this->assertSame('Hipertensión arterial', $p->chronic_conditions);
        $raw = \DB::table('aero_clinica_patients')->where('id', $p->id)->first();
        $this->assertStringNotContainsString('Hipertensión', (string) $raw->chronic_conditions, 'Enfermedades crónicas cifradas');
        $this->assertStringNotContainsString('fuma', (string) $raw->habits, 'Hábitos cifrados');
        $this->assertContains('chronic_conditions', Patient::CLINICAL);
    }

    public function testPrescriptionKeepsActiveIngredientStrengthAndPresentation(): void
    {
        $c = $this->note($this->patient());
        $med = Medication::create(['tenant_id' => 1, 'name' => 'Amoxicilina', 'concentration' => '500 mg', 'form' => 'cápsulas']);

        $it = (new ClinicalItems())->addPrescriptionItem($c, ['source' => 'custom', 'ref_id' => $med->id, 'dose' => '1 cápsula', 'route' => 'oral', 'frequency' => 'cada 8 h', 'duration' => '7 días', 'quantity' => '21'], $this->user());
        $this->assertSame('Amoxicilina', $it->active_ingredient);
        $this->assertSame('500 mg', $it->strength);
        $this->assertSame('cápsulas', $it->presentation);

        $free = (new ClinicalItems())->addPrescriptionItem($c, ['medication' => 'Paracetamol', 'active_ingredient' => 'Paracetamol', 'strength' => '1 g', 'presentation' => 'tabletas', 'dose' => '1 tableta'], $this->user());
        $raw = \DB::table('aero_clinica_prescription_items')->where('id', $free->id)->first();
        $this->assertStringNotContainsString('Paracetamol', (string) $raw->active_ingredient, 'Cifrado en reposo');
        $this->assertSame('1 g', PrescriptionItem::find($free->id)->strength);
    }

    public function testLabResultUsesItsOwnReferenceAndFlagsOutOfRange(): void
    {
        $p = $this->patient();
        $items = new ClinicalItems();

        $ok = $items->addLab($p, ['analyte_name' => 'Hemoglobina', 'value' => '13,5', 'unit' => 'g/dL', 'ref_low' => '12', 'ref_high' => '16', 'lab_name' => 'Lab Central'], $this->user());
        $low = $items->addLab($p, ['analyte_name' => 'Hemoglobina', 'value' => '9', 'unit' => 'g/dL', 'ref_low' => '12', 'ref_high' => '16'], $this->user());
        $high = $items->addLab($p, ['analyte_name' => 'Glucosa', 'value' => '140', 'unit' => 'mg/dL', 'ref_high' => '99'], $this->user());

        $this->assertNull($ok->flag());
        $this->assertSame('low', $low->flag());
        $this->assertSame('high', $high->flag());
        $this->assertSame('12 – 16', $ok->referenceText());
        $this->assertSame('≤ 99', $high->referenceText());
        $this->assertSame('Lab Central', $ok->lab_name);

        try {
            $items->addLab($p, ['analyte_name' => 'X', 'value' => '1', 'ref_low' => '10', 'ref_high' => '5'], $this->user());
            $this->fail('Referencia invertida');
        } catch (ClinicaException $e) {
            $this->assertStringContainsString('límite bajo', $e->getMessage());
        }
    }

    public function testResultAnswersTheRequestAndOnlyOnce(): void
    {
        $p = $this->patient();
        $c = $this->note($p);
        $items = new ClinicalItems();
        $req = $items->addOrderItem($c, ['exam_name' => 'Hemograma completo'], $this->user());
        $this->assertSame('requested', OrderItem::find($req->id)->status, 'Una solicitud no es un resultado');
        $this->assertSame(0, LabResult::count());

        $items->addLab($p, ['analyte_name' => 'Hemoglobina', 'value' => '14', 'order_item_id' => $req->id], $this->user());
        $this->assertSame('resulted', OrderItem::find($req->id)->status);
        $this->assertSame($req->id, (int) LabResult::first()->order_item_id);
    }

    public function testResultCannotCloseAnotherPatientsRequest(): void
    {
        $c = $this->note($this->patient());
        $items = new ClinicalItems();
        $req = $items->addOrderItem($c, ['exam_name' => 'Hemograma'], $this->user());

        $other = $this->patient(1, ['name' => 'Otra Persona']);
        $items->addLab($other, ['analyte_name' => 'Hemoglobina', 'value' => '14', 'order_item_id' => $req->id], $this->user());

        $this->assertSame('requested', OrderItem::find($req->id)->status);
        $this->assertNull(LabResult::first()->order_item_id);
    }

    public function testVaccinationRecordsLotManufacturerAndSite(): void
    {
        $p = $this->patient();
        $v = (new VaccineService())->apply($p, [
            'vaccine_name' => 'Hepatitis B', 'dose_label' => '1.ª dosis', 'applied_on' => now()->toDateString(),
            'lot' => 'L-2291', 'manufacturer' => 'Laboratorio X', 'route_site' => 'IM, deltoides izq.',
        ], $this->user());

        $v = Vaccination::find($v->id);
        $this->assertSame('L-2291', $v->lot);
        $this->assertSame('Laboratorio X', $v->manufacturer);
        $this->assertSame('IM, deltoides izq.', $v->route_site);
    }

    public function testDefaultMedicalDocumentsLoadOnceAndRenderWithPatientData(): void
    {
        $r = DefaultDocuments::load(1);
        $this->assertSame(5, $r['created']);
        $this->assertSame(0, DefaultDocuments::load(1)['created'], 'No duplica');
        $this->assertEqualsCanonicalizing(['certificate', 'constancia', 'report', 'consent', 'referral'], DocumentTemplate::where('tenant_id', 1)->pluck('kind')->all());
        $this->assertSame(0, DocumentTemplate::where('tenant_id', 2)->count(), 'Por negocio');

        $p = new Patient(['tenant_id' => 1, 'name' => 'Luis Rojas', 'document' => '1234567']); // sin guardar: no crea cliente en Office
        $t = DocumentTemplate::where('tenant_id', 1)->where('kind', 'referral')->first();
        $text = DocumentRenderer::render($t->body, $p, $this->user());
        $this->assertStringContainsString('Luis Rojas', $text);
        $this->assertStringContainsString('1234567', $text);
        $this->assertStringNotContainsString('{{', $text);
    }

    public function testNoVeterinaryLeftovers(): void
    {
        $this->assertFalse(\Schema::hasColumn('aero_clinica_patients', 'species'));
        $this->assertFalse(\Schema::hasColumn('aero_clinica_patients', 'microchip'));
        $this->assertFalse(\Schema::hasColumn('aero_clinica_patients', 'kind'));
        $this->assertFalse(\Schema::hasColumn('aero_clinica_settings', 'mode'));
        $this->assertFalse(\Schema::hasTable('aero_clinica_dewormings'));
        $this->assertFalse(\Schema::hasTable('aero_clinica_grooming_visits'));
        $this->assertFalse(class_exists(\Aero\Clinica\Classes\Mode::class));
    }
}
