<?php namespace Aero\Sheets\Tests;

use PluginTestCase;

class SmokeTest extends PluginTestCase
{
    public function testMigrationsCreateTables(): void
    {
        foreach (['aero_sheets_sources', 'aero_sheets_mappings', 'aero_sheets_runs'] as $table) {
            $this->assertTrue(\Schema::hasTable($table), "Falta la tabla {$table}");
        }
    }

    public function testNavigationRequiresDeclaredPermissions(): void
    {
        $plugin = new \Aero\Sheets\Plugin($this->app);
        $declared = array_keys($plugin->registerPermissions());

        foreach ($plugin->registerNavigation() as $item) {
            foreach (array_merge($item['permissions'] ?? [], ...array_column($item['sideMenu'] ?? [], 'permissions')) as $p) {
                $this->assertContains($p, $declared);
            }
        }
    }

    public function testSheetsScopeIsOnlyAnnouncedWhileThePluginIsBooted(): void
    {
        $scopes = \Aero\Oauth\Classes\ScopeRegistry::resolve('google', ['sheets', 'inexistente']);
        $this->assertSame([\Aero\Sheets\Classes\SheetsClient::SCOPE], $scopes);
    }
}
