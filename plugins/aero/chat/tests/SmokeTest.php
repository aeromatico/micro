<?php namespace Aero\Chat\Tests;

use PluginTestCase;

/**
 * Humo: el Plugin.php carga y su menú/permisos son coherentes. Atrapa el error
 * clásico de un return faltante o un permiso del menú sin declarar, que tumba el panel.
 */
class SmokeTest extends PluginTestCase
{
    protected $autoRegister = false;

    protected function migrateCurrentPlugin()
    {
    }

    public function testPluginDetailsAndNavigationAreCoherent(): void
    {
        $plugin = new \Aero\Chat\Plugin($this->app);

        $this->assertNotEmpty($plugin->pluginDetails()['name'] ?? null);

        $declared = method_exists($plugin, 'registerPermissions') ? array_keys($plugin->registerPermissions()) : [];
        $nav = method_exists($plugin, 'registerNavigation') ? $plugin->registerNavigation() : [];
        $this->assertIsArray($nav);

        foreach ($nav as $item) {
            // Solo los permisos propios: un menú puede exigir permisos de otro plugin (válido).
            foreach (array_filter(array_merge($item['permissions'] ?? [], ...array_values(array_map(fn ($s) => $s["permissions"] ?? [], $item["sideMenu"] ?? []))), fn ($x) => str_starts_with($x, 'aero.chat.')) as $perm) {
                $this->assertContains($perm, $declared, "El menú exige un permiso no declarado: {$perm}");
            }
        }
    }
}
