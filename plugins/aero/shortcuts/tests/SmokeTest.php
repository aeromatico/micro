<?php namespace Aero\Shortcuts\Tests;

use PluginTestCase;

/**
 * Humo mínimo (copiar a plugins/aero/shortcuts/tests/SmokeTest.php). Ejemplo completo
 * y funcionando: plugins/aero/sms/tests/SmokeTest.php.
 * Correr: bin/verify-plugin shortcuts   o   bin/aero-test shortcuts
 */
class SmokeTest extends PluginTestCase
{
    public function testMigrationsCreateTables(): void
    {
        foreach (['aero_shortcuts_items'] as $table) {   // ← tablas del plugin
            $this->assertTrue(\Schema::hasTable($table), "Falta la tabla {$table}");
        }
    }

    public function testNavigationRequiresDeclaredPermissions(): void
    {
        $plugin = new \Aero\Shortcuts\Plugin($this->app);
        $declared = array_keys($plugin->registerPermissions());

        foreach ($plugin->registerNavigation() as $item) {
            foreach (($item['permissions'] ?? []) as $p) {
                $this->assertContains($p, $declared, "El menú exige un permiso no declarado: {$p}");
            }
        }
    }

    public function testTenantIsolationAndHref(): void
    {
        $a = \Aero\Shortcuts\Models\Item::create(['tenant_id' => 1, 'label' => 'A', 'kind' => 'url', 'url' => 'https://example.com']);
        \Aero\Shortcuts\Models\Item::create(['tenant_id' => 2, 'label' => 'B', 'kind' => 'list', 'target' => 'aero/sms/messages']);

        $this->assertSame([$a->id], \Aero\Shortcuts\Models\Item::where('tenant_id', 1)->pluck('id')->all());
        $this->assertTrue($a->opens_new_tab);
        $this->assertSame('https://example.com', $a->href);

        $f = new \Aero\Shortcuts\Models\Item(['kind' => 'form', 'target' => 'aero/sms/templates']);
        $this->assertStringEndsWith('aero/sms/templates/create', $f->href);
    }
}

