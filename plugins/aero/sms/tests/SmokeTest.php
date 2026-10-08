<?php namespace Aero\Sms\Tests;

use Aero\Sms\Classes\PhoneNumber;
use Aero\Sms\Classes\Segments;
use Aero\Sms\Models\Template;
use PluginTestCase;

/**
 * Humo mínimo: el plugin carga, migra, declara permisos coherentes y el
 * aislamiento por tenant en los modelos funciona. Plantilla de referencia para
 * los tests de los demás plugins (ver .claude/skills/aero-plugin).
 */
class SmokeTest extends PluginTestCase
{
    public function testMigrationsCreateTables(): void
    {
        foreach (['aero_sms_messages', 'aero_sms_batches', 'aero_sms_templates', 'aero_sms_optouts'] as $table) {
            $this->assertTrue(\Schema::hasTable($table), "Falta la tabla {$table}");
        }
    }

    public function testPermissionsAreDeclared(): void
    {
        $plugin = new \Aero\Sms\Plugin($this->app);
        $perms = array_keys($plugin->registerPermissions());

        $this->assertContains('aero.sms.use', $perms);
        $this->assertContains('aero.sms.superadmin', $perms);
    }

    public function testNavigationRequiresDeclaredPermissions(): void
    {
        $plugin = new \Aero\Sms\Plugin($this->app);
        $declared = array_keys($plugin->registerPermissions());

        foreach ($plugin->registerNavigation() as $item) {
            foreach (($item['permissions'] ?? []) as $p) {
                $this->assertContains($p, $declared, "El menú exige un permiso no declarado: {$p}");
            }
            foreach (($item['sideMenu'] ?? []) as $side) {
                foreach (($side['permissions'] ?? []) as $p) {
                    $this->assertContains($p, $declared, "El submenú exige un permiso no declarado: {$p}");
                }
            }
        }
    }

    public function testTemplateSlugIsUniquePerTenant(): void
    {
        Template::create(['tenant_id' => 1, 'name' => 'A', 'slug' => 'hola', 'body' => 'x', 'is_active' => true]);

        // Otro tenant puede reutilizar el slug…
        $other = Template::create(['tenant_id' => 2, 'name' => 'B', 'slug' => 'hola', 'body' => 'y', 'is_active' => true]);
        $this->assertNotNull($other->id);

        // …pero el mismo tenant no.
        $this->expectException(\October\Rain\Database\ModelException::class);
        Template::create(['tenant_id' => 1, 'name' => 'C', 'slug' => 'hola', 'body' => 'z', 'is_active' => true]);
    }

    public function testTenantTemplateWinsOverGlobal(): void
    {
        Template::create(['tenant_id' => null, 'name' => 'G', 'slug' => 'bienvenida', 'body' => 'global', 'is_active' => true]);
        Template::create(['tenant_id' => 7, 'name' => 'P', 'slug' => 'bienvenida', 'body' => 'propia', 'is_active' => true]);

        $this->assertSame('propia', Template::findForTenant('bienvenida', 7)->body);
        $this->assertSame('global', Template::findForTenant('bienvenida', 8)->body);
    }

    public function testPhoneNumberNormalizesBolivianNumbers(): void
    {
        $this->assertSame('+59170000000', PhoneNumber::normalize('70000000'));
        $this->assertNull(PhoneNumber::normalize(''));
    }

    public function testShortSmsIsOneSegment(): void
    {
        $this->assertSame(1, Segments::count('Hola')['segments']);
    }
}
