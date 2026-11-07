<?php namespace Aero\Clinica\Tests;

use Aero\Clinica\Classes\ChargeService;
use Aero\Clinica\Classes\ClinicaException;
use Aero\Clinica\Classes\Ranges;
use Aero\Clinica\Classes\ReferenceRanges;
use Aero\Clinica\Classes\StarterDiagnoses;
use Aero\Clinica\Classes\SuggestedTemplates;
use Aero\Clinica\Http\Controllers\Api\PatientsController;
use Aero\Clinica\Models\Charge;
use Aero\Clinica\Models\ClinicSettings;
use Aero\Clinica\Models\DiagnosisCatalog;
use Aero\Clinica\Models\NoteTemplate;
use Aero\Clinica\Models\Patient;
use Aero\Clinica\Models\Range;
use Illuminate\Http\Request;
use PluginTestCase;

class Phase6Test extends PluginTestCase
{
    protected function user(): object
    {
        return (object) ['id' => 7, 'first_name' => 'Ana', 'last_name' => 'Cajera', 'email' => 'u7@example.test'];
    }

    protected function tenant(int $id, string $mode = 'human'): void
    {
        if (!ClinicSettings::where('tenant_id', $id)->exists()) {
            ClinicSettings::create(['tenant_id' => $id]);
        }
    }

    protected function patient(int $tenant = 1, array $extra = []): Patient
    {
        $file = base_path('plugins/aero/office/updates/create_office_tables.php');
        if (is_file($file) && !\Schema::hasTable('aero_office_customers')) {
            (require $file)->up(); // Office está instalado: sus tablas deben existir en el harness
        }
        $this->tenant($tenant);
        $p = new Patient($extra + ['tenant_id' => $tenant, 'name' => 'Paciente ' . uniqid()]);
        $p->save();

        return $p;
    }

    public function testMigrationsCreateTablesAndColumns(): void
    {
        $this->assertTrue(\Schema::hasTable('aero_clinica_charges'));
        $this->assertTrue(\Schema::hasColumn('aero_clinica_ranges', 'source'));
    }

    // ---- Rangos de referencia ----

    public function testReferenceRangesLoadAsDraftAndNeverOverwrite(): void
    {
        $this->tenant(1);
        Range::create(['tenant_id' => 1, 'metric' => 'heart_rate', 'low' => 50, 'high' => 110]);

        $r = ReferenceRanges::load(1);
        $this->assertGreaterThan(8, $r['created']);
        $this->assertSame(1, $r['skipped'], 'El rango propio de frecuencia cardíaca no se toca');

        $own = Range::where('tenant_id', 1)->where('metric', 'heart_rate')->first();
        $this->assertEquals(110, $own->high);
        $this->assertSame('custom', $own->source);

        $sys = Range::where('tenant_id', 1)->where('metric', 'systolic')->first();
        $this->assertSame('reference', $sys->source);
        $this->assertEquals(139, $sys->high);

        $this->assertSame($r['skipped'] + $r['created'], count(ReferenceRanges::VITALS) + 4);
        $again = ReferenceRanges::load(1);
        $this->assertSame(0, $again['created'], 'Idempotente');
    }

    public function testEditingAReferenceRangeMakesItTheBusinessOwn(): void
    {
        $this->tenant(1);
        ReferenceRanges::load(1);
        $r = Range::where('tenant_id', 1)->where('metric', 'systolic')->first();
        $r->high = 150;
        $r->save();

        $this->assertSame('custom', $r->fresh()->source);
    }

    public function testReferenceRangesFlagValuesAndAnalytesLinkByName(): void
    {
        $this->tenant(1);
        ReferenceRanges::load(1);
        Ranges::flush();

        $this->assertSame('high', Ranges::flag(1, null, 'systolic', 150));
        $this->assertNull(Ranges::flag(1, null, 'systolic', 139));
        $this->assertSame('low', Ranges::flag(1, null, 'spo2', 90));
        $this->assertSame('high', Ranges::flag(1, null, 'bmi', 27.7));

        $glucose = \Aero\Clinica\Models\Analyte::where('tenant_id', 1)->where('name', 'Glucosa en ayunas')->first();
        $this->assertSame('high', Ranges::flag(1, null, 'analyte:' . $glucose->id, 126));
        $this->assertNull(Ranges::flag(1, null, 'analyte:' . $glucose->id, 90));
            }

    // ---- Plantillas sugeridas ----

    public function testSuggestedTemplatesAreAddedOnceWithValidKindsAndSingleVitalsSection(): void
    {
        $this->tenant(1);
        $r = SuggestedTemplates::load(1);
        $this->assertSame(2, $r['created']);
        $this->assertSame(0, SuggestedTemplates::load(1)['created']);

        $kinds = NoteTemplate::where('tenant_id', 1)->pluck('kind', 'name')->all();
        $this->assertSame('control', $kinds['Control de paciente crónico']);
        $this->assertSame('consultation', $kinds['Chequeo preventivo']);
        foreach (NoteTemplate::where('tenant_id', 1)->get() as $t) {
            $this->assertCount(1, array_filter($t->sections, fn ($s) => $s['type'] === 'vitals'));
            $this->assertContains('reason', array_column($t->sections, 'key'));
        }
    }

    // ---- Lista inicial de medicina general ----

    public function testStarterDiagnosesUseOnlyExistingCodesWithOfficialTitles(): void
    {
        $this->tenant(1);
        \Artisan::call('clinica:import-cie10');

        $r = StarterDiagnoses::load(1);
        $this->assertSame([], $r['missing'], 'Todos los códigos de la lista existen en el CIE-10 cargado');
        $this->assertSame(count(StarterDiagnoses::CODES), $r['created']);
        $this->assertSame('Hipertensión esencial (primaria)', DiagnosisCatalog::where('tenant_id', 1)->where('code', 'I10')->value('label'));
        $this->assertSame(0, StarterDiagnoses::load(1)['created']);
        $this->assertSame(0, DiagnosisCatalog::where('tenant_id', 1)->where('pinned', true)->count(), 'No fija nada: es de partida');
    }

    public function testStarterDiagnosesRefuseWithoutCatalog(): void
    {
        $this->tenant(1);
        try {
            StarterDiagnoses::load(1);
            $this->fail('Sin catálogo CIE-10 cargado');
        } catch (ClinicaException $e) {
            $this->assertStringContainsString('CIE-10', $e->getMessage());
        }
    }

    // ---- Cobros ----

    public function testCashChargeIsPaidAtOnceAndFiresOnlyIds(): void
    {
        $p = $this->patient();
        $events = [];
        \Event::listen('aero.clinica.chargePaid', function ($payload) use (&$events) {
            $events[] = $payload;
        });

        $c = (new ChargeService())->create($p, ['concept' => 'Consulta', 'amount' => '150,50', 'method' => 'cash'], $this->user());

        $this->assertSame('paid', $c->fresh()->status);
        $this->assertEquals(150.50, $c->fresh()->amount);
        $this->assertNotNull($c->fresh()->paid_at);
        $this->assertSame([['charge_id' => $c->id, 'patient_id' => $p->id, 'tenant_id' => 1]], $events, 'Solo ids: nada clínico ni montos hacia webhooks');
        $this->assertStringNotContainsString('Consulta', (string) \DB::table('aero_clinica_charges')->where('id', $c->id)->value('concept'), 'El concepto va cifrado');
    }

    public function testChargeValidation(): void
    {
        $p = $this->patient();
        foreach ([['amount' => ''], ['amount' => '0'], ['amount' => '-5'], ['amount' => 'abc'], ['amount' => '10000000'], ['amount' => '10', 'currency' => 'EUR'], ['amount' => '10', 'method' => 'cripto']] as $bad) {
            try {
                (new ChargeService())->create($p, $bad, $this->user());
                $this->fail('Debió rechazar ' . json_encode($bad));
            } catch (ClinicaException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
        $this->assertSame(0, Charge::count());
    }

    public function testChargeRejectsAnotherPatientsNote(): void
    {
        $a = $this->patient();
        $b = $this->patient();
        $note = (new \Aero\Clinica\Classes\ConsultationService())->start($b, 'consultation', $this->user());

        $this->expectException(ClinicaException::class);
        (new ChargeService())->create($a, ['amount' => '10', 'consultation_id' => $note->id], $this->user());
    }

    public function testQrChargeNeedsPayAndAnActiveAccount(): void
    {
        $p = $this->patient();

        $this->expectException(ClinicaException::class);
        (new ChargeService())->create($p, ['amount' => '10', 'method' => 'qr', 'bank_account_id' => 999], $this->user());
    }

    public function testQrFailureLeavesNoChargeBehindAndStatusSyncWorks(): void
    {
        $p = $this->patient();
        $c = new Charge(['tenant_id' => 1, 'patient_id' => $p->id, 'concept' => 'Atención', 'amount' => 20, 'currency' => 'BOB', 'method' => 'qr', 'qr_code_id' => 55]);
        $c->status = 'pending';
        $c->save();

        // Un QR de otro módulo no toca el cobro.
        ChargeService::onQrStatus((object) ['id' => 55, 'origin' => 'shop', 'status' => 'paid']);
        $this->assertSame('pending', $c->fresh()->status);

        $events = [];
        \Event::listen('aero.clinica.chargePaid', function ($payload) use (&$events) {
            $events[] = $payload;
        });
        ChargeService::onQrStatus((object) ['id' => 55, 'origin' => 'clinica', 'status' => 'paid']);
        $this->assertSame('paid', $c->fresh()->status);
        $this->assertCount(1, $events);

        ChargeService::onQrStatus((object) ['id' => 55, 'origin' => 'clinica', 'status' => 'paid']);
        $this->assertCount(1, $events, 'Un aviso repetido del banco no cobra dos veces');
    }

    public function testExpiredQrCancelsAPendingChargeAndVoidingAPaidOneFiresVoid(): void
    {
        $p = $this->patient();
        $svc = new ChargeService();
        $pending = new Charge(['tenant_id' => 1, 'patient_id' => $p->id, 'concept' => 'x', 'amount' => 5, 'currency' => 'BOB', 'method' => 'qr', 'qr_code_id' => 77]);
        $pending->status = 'pending';
        $pending->save();
        ChargeService::onQrStatus((object) ['id' => 77, 'origin' => 'clinica', 'status' => 'expired']);
        $this->assertSame('cancelled', $pending->fresh()->status);

        $voids = [];
        \Event::listen('aero.clinica.chargeVoided', function ($payload) use (&$voids) {
            $voids[] = $payload;
        });
        $paid = $svc->create($p, ['amount' => '30', 'method' => 'transfer'], $this->user());
        $svc->cancel($paid, 'Error de monto');
        $this->assertSame('cancelled', $paid->fresh()->status);
        $this->assertCount(1, $voids, 'Anular un cobro cobrado avisa a Finanzas');

        $this->expectException(ClinicaException::class);
        $svc->cancel($paid->fresh());
    }

    public function testChargesAreIsolatedByTenant(): void
    {
        $svc = new ChargeService();
        $a = $svc->create($this->patient(1), ['amount' => '10'], $this->user());
        $svc->create($this->patient(2), ['amount' => '20'], $this->user());

        $this->assertSame([$a->id], Charge::forTenant(1)->pluck('id')->all());
        $this->assertCount(0, Charge::visible()->get());
    }

    // ---- API ----

    protected function apiRequest(?int $tenant, array $query = []): Request
    {
        $r = Request::create('/api/v1/clinica/patients', 'GET', $query);
        $r->attributes->set('api_key', (object) ['owner_type' => $tenant ? \Aero\Sites\Models\Tenant::class : null, 'owner_id' => $tenant]);

        return $r;
    }

    public function testApiReturnsOnlyAdministrativeFieldsOfTheKeysBusiness(): void
    {
        $mine = $this->patient(1, ['name' => 'Ana API', 'document' => '123', 'allergies' => 'Penicilina-SECRETA', 'clinical_notes' => 'NOTA-SECRETA']);
        $this->patient(2, ['name' => 'Ajeno API']);
        $ctrl = new PatientsController();

        $body = $ctrl->index($this->apiRequest(1))->getContent();
        $json = json_decode($body, true);

        $this->assertCount(1, $json['data']);
        $this->assertSame('Ana API', $json['data'][0]['name']);
        $this->assertStringNotContainsString('Penicilina', $body);
        $this->assertStringNotContainsString('SECRETA', $body);
        $this->assertStringNotContainsString('Ajeno', $body);
        $this->assertEqualsCanonicalizing(PatientsController::FIELDS, array_keys($json['data'][0]));

        $this->assertSame(200, $ctrl->show($this->apiRequest(1), $mine->id)->getStatusCode());
        $this->assertSame(404, $ctrl->show($this->apiRequest(2), $mine->id)->getStatusCode(), 'Un paciente de otro negocio no existe para esta key');
    }

    public function testApiRefusesKeysWithoutABusiness(): void
    {
        $this->patient(1);
        $res = (new PatientsController())->index($this->apiRequest(null));

        $this->assertSame(403, $res->getStatusCode());
        $this->assertStringContainsString('tenant_required', $res->getContent());
    }

    public function testApiSearchAndFilters(): void
    {
        $this->patient(1, ['name' => 'Beto Buscado', 'document' => '777']);
        $this->patient(1, ['name' => 'Otra Persona']);

        $json = json_decode((new PatientsController())->index($this->apiRequest(1, ['q' => 'buscado']))->getContent(), true);
        $this->assertSame(['Beto Buscado'], array_column($json['data'], 'name'));
    }
}
