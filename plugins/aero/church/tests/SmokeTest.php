<?php namespace Aero\Church\Tests;

use Aero\Church\Models\Branch;
use Aero\Church\Models\Member;
use PluginTestCase;

class SmokeTest extends PluginTestCase
{
    public function testMigrationsCreateTables(): void
    {
        foreach (['aero_church_branches', 'aero_church_families', 'aero_church_members'] as $table) {
            $this->assertTrue(\Schema::hasTable($table), "Falta la tabla {$table}");
        }
    }

    public function testNavigationRequiresDeclaredPermissions(): void
    {
        $plugin = new \Aero\Church\Plugin($this->app);
        $declared = array_keys($plugin->registerPermissions());

        foreach ($plugin->registerNavigation() as $item) {
            foreach (($item['permissions'] ?? []) as $p) {
                $this->assertContains($p, $declared);
            }
        }
    }

    public function testTenantIsolation(): void
    {
        Member::create(['tenant_id' => 1, 'first_name' => 'Ana', 'status' => 'active']);
        Member::create(['tenant_id' => 2, 'first_name' => 'Beto', 'status' => 'active']);
        Branch::create(['tenant_id' => 1, 'name' => 'Central']);

        $this->assertSame(['Ana'], Member::forTenant(1)->pluck('first_name')->all());
        $this->assertSame(['Beto'], Member::forTenant(2)->pluck('first_name')->all());
        $this->assertSame(0, Member::forTenant(null)->count(), 'Sin tenant no debe ver nada');
        $this->assertSame(0, Branch::forTenant(2)->count());
    }

    public function testMinorAndConsent(): void
    {
        $m = Member::create(['tenant_id' => 1, 'first_name' => 'Niño', 'status' => 'new',
            'birth_date' => now()->subYears(8)->toDateString(), 'consent_messages' => true]);

        $this->assertTrue($m->is_minor);
        $this->assertNotNull($m->consent_at);

        $m->update(['consent_messages' => false]);
        $this->assertNull($m->fresh()->consent_at);
    }
}
