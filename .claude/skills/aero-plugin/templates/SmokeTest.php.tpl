<?php namespace Aero\{Plugin}\Tests;

use PluginTestCase;

/**
 * Humo mínimo (copiar a plugins/aero/{p}/tests/SmokeTest.php). Ejemplo completo
 * y funcionando: plugins/aero/sms/tests/SmokeTest.php.
 * Correr: bin/verify-plugin {p}   o   bin/aero-test {p}
 */
class SmokeTest extends PluginTestCase
{
    public function testMigrationsCreateTables(): void
    {
        foreach (['aero_{p}_xxx'] as $table) {   // ← tablas del plugin
            $this->assertTrue(\Schema::hasTable($table), "Falta la tabla {$table}");
        }
    }

    public function testNavigationRequiresDeclaredPermissions(): void
    {
        $plugin = new \Aero\{Plugin}\Plugin($this->app);
        $declared = array_keys($plugin->registerPermissions());

        foreach ($plugin->registerNavigation() as $item) {
            foreach (($item['permissions'] ?? []) as $p) {
                $this->assertContains($p, $declared, "El menú exige un permiso no declarado: {$p}");
            }
        }
    }

    // Aislamiento por tenant: crear datos para tenant 1 y 2 y comprobar que el
    // scope del controller/servicio de cada uno solo ve lo suyo (y que sin
    // tenant resoluble no ve nada).
    // public function testTenantIsolation(): void { ... }
}
