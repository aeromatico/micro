<?php namespace Aero\Medical\Tests;

use Aero\Medical\Classes\Audit;
use Aero\Medical\Classes\Mode;
use Aero\Medical\Models\AuditLog;
use Aero\Medical\Models\ClinicSettings;
use Aero\Medical\Models\Patient;
use PluginTestCase;

class PatientTest extends PluginTestCase
{
    /** Office no se arranca como plugin (requiere RainLab.User): solo sus tablas. */
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

    /** Un tutor existente: con Office instalado todo animal exige tutor. */
    protected function guardian(int $tenant): array
    {
        if (!$this->officeTables()) {
            return [];
        }

        return ['customer_id' => \Aero\Office\Models\Customer::create(['tenant_id' => $tenant, 'name' => 'Tutor', 'phone' => '7000000' . $tenant])->id];
    }

    protected function tenant(int $id, string $mode): void
    {
        ClinicSettings::create(['tenant_id' => $id, 'mode' => $mode]);
    }

    public function testMigrationsCreateTables(): void
    {
        foreach (['settings', 'patients', 'audit_logs'] as $t) {
            $this->assertTrue(\Schema::hasTable('aero_medical_' . $t), "Falta aero_medical_{$t}");
        }
    }

    public function testNavigationRequiresDeclaredPermissions(): void
    {
        $plugin = new \Aero\Medical\Plugin($this->app);
        $declared = array_keys($plugin->registerPermissions());

        foreach ($plugin->registerNavigation() as $item) {
            foreach (array_merge($item['permissions'] ?? [], ...array_column($item['sideMenu'] ?? [], 'permissions')) as $p) {
                $this->assertContains($p, $declared, "El menú exige un permiso no declarado: {$p}");
            }
        }
    }

    public function testModeIsDataNotBranching(): void
    {
        $this->assertSame('Tutor', Mode::term('guardian', Mode::ANIMAL));
        $this->assertSame('Médico', Mode::term('professional', Mode::HUMAN));
        $this->assertSame('Veterinario', Mode::term('professional', Mode::ANIMAL));
        $this->assertContains('species', Mode::hiddenFields(Mode::HUMAN));
        $this->assertContains('blood_type', Mode::hiddenFields(Mode::ANIMAL));
        $this->assertNull(Mode::forTenant(99), 'Sin configuración no hay modo');
    }

    public function testTenantIsolation(): void
    {
        $this->tenant(1, 'human');
        $this->tenant(2, 'human');
        Patient::create(['tenant_id' => 1, 'name' => 'Ana']);
        Patient::create(['tenant_id' => 2, 'name' => 'Beto']);

        $this->assertSame(['Ana'], Patient::forTenant(1)->pluck('name')->all());
        $this->assertSame(['Beto'], Patient::forTenant(2)->pluck('name')->all());
        $this->assertCount(0, Patient::forTenant(null)->get(), 'Sin tenant no se ve nada (falla cerrado)');
        $this->assertCount(0, Patient::visible()->get(), 'Sin usuario ni tenant resoluble no se ve nada');
    }

    public function testClinicalTextIsEncryptedAtRest(): void
    {
        $this->tenant(1, 'human');
        $p = Patient::create(['tenant_id' => 1, 'name' => 'Ana', 'allergies' => 'Penicilina']);

        $raw = \DB::table('aero_medical_patients')->where('id', $p->id)->value('allergies');
        $this->assertStringNotContainsString('Penicilina', (string) $raw);
        $this->assertSame('Penicilina', Patient::find($p->id)->allergies);
    }

    public function testKindIsFrozenWhenModeChanges(): void
    {
        $this->tenant(1, 'animal');
        $p = Patient::create(['tenant_id' => 1, 'name' => 'Firulais', 'species' => 'dog'] + $this->guardian(1));
        $this->assertSame('animal', $p->kind);

        $s = ClinicSettings::where('tenant_id', 1)->first();
        $s->mode = 'human';
        $s->confirm_mode_change = true;
        $s->save();
        $this->assertSame('animal', Patient::find($p->id)->kind, 'Cambiar el modo no reinterpreta los pacientes existentes');
    }

    public function testModeChangeWithPatientsNeedsConfirmation(): void
    {
        $this->tenant(1, 'human');
        Patient::create(['tenant_id' => 1, 'name' => 'Ana']);
        $s = ClinicSettings::where('tenant_id', 1)->first();

        $s->mode = 'animal';
        try {
            $s->save();
            $this->fail('Debió pedir confirmación');
        } catch (\ApplicationException $e) {
            $this->assertStringContainsString('Confirmo', $e->getMessage());
        }
        $this->assertSame('human', ClinicSettings::where('tenant_id', 1)->value('mode'));

        $s = ClinicSettings::where('tenant_id', 1)->first();
        $s->mode = 'animal';
        $s->confirm_mode_change = true;
        $s->save();
        $this->assertSame('animal', ClinicSettings::where('tenant_id', 1)->value('mode'));
        $this->assertSame(1, Patient::where('tenant_id', 1)->count(), 'No se borró nada');
    }

    public function testModeChangeWithoutPatientsIsFree(): void
    {
        $this->tenant(1, 'human');
        $s = ClinicSettings::where('tenant_id', 1)->first();
        $s->mode = 'animal';
        $s->save();
        $this->assertSame('animal', Mode::forTenant(1));
    }

    public function testAuditRecordsFieldNamesNeverValues(): void
    {
        $this->tenant(1, 'human');
        $p = Patient::create(['tenant_id' => 1, 'name' => 'Ana']);
        $p->update(['allergies' => 'Penicilina']);

        $log = AuditLog::where('patient_id', $p->id)->where('action', 'update')->first();
        $this->assertNotNull($log);
        $this->assertContains('allergies', json_decode($log->fields, true));
        $this->assertStringNotContainsString('Penicilina', json_encode($log->toArray()));
        $this->assertTrue(AuditLog::where('patient_id', $p->id)->where('action', 'create')->exists());
    }

    public function testAuditLogIsImmutable(): void
    {
        Audit::record('view', 1, 5, 'patient', 5);
        $log = AuditLog::first();

        $this->expectException(\ApplicationException::class);
        $log->update(['action' => 'delete']);
    }

    public function testAuditLogCannotBeDeleted(): void
    {
        Audit::record('view', 1, 5, 'patient', 5);

        $this->expectException(\ApplicationException::class);
        AuditLog::first()->delete();
    }

    public function testAnimalWithoutGuardianIsRejectedWhenOfficeExists(): void
    {
        if (!$this->officeTables()) {
            $this->markTestSkipped('Sin Aero.Office el tutor es opcional');
        }
        $this->tenant(1, 'animal');

        $this->expectException(\ApplicationException::class);
        Patient::create(['tenant_id' => 1, 'name' => 'Firulais', 'species' => 'dog']);
    }

    public function testAnimalGuardianIsCreatedInOfficeAndReused(): void
    {
        if (!$this->officeTables()) {
            $this->markTestSkipped('Requiere Aero.Office');
        }
        $this->tenant(1, 'animal');

        $a = new Patient(['tenant_id' => 1, 'name' => 'Firulais', 'species' => 'dog']);
        $a->contact_name = 'María Tutora';
        $a->contact_phone = '70000001';
        $a->save();

        $b = new Patient(['tenant_id' => 1, 'name' => 'Michi', 'species' => 'cat']);
        $b->contact_name = 'María Tutora';
        $b->contact_phone = '70000001';
        $b->save();

        $this->assertNotNull($a->customer_id);
        $this->assertSame($a->customer_id, $b->customer_id, 'Un tutor puede tener varios animales sin duplicarse');
        $this->assertSame(1, \Aero\Office\Models\Customer::where('tenant_id', 1)->count());
    }

    public function testGuardianCustomerOfAnotherTenantIsNotReused(): void
    {
        if (!$this->officeTables()) {
            $this->markTestSkipped('Requiere Aero.Office');
        }
        $this->tenant(2, 'animal');
        \Aero\Office\Models\Customer::create(['tenant_id' => 1, 'name' => 'Ajeno', 'phone' => '70000009']);

        $p = new Patient(['tenant_id' => 2, 'name' => 'Rex', 'species' => 'dog']);
        $p->contact_name = 'Ajeno';
        $p->contact_phone = '70000009';
        $p->save();

        $this->assertSame(2, (int) \Aero\Office\Models\Customer::find($p->customer_id)->tenant_id);
    }
}
