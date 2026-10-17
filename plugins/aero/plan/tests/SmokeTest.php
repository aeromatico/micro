<?php namespace Aero\Plan\Tests;

use PluginTestCase;

class SmokeTest extends PluginTestCase
{
    public function testMigrationsCreateTables(): void
    {
        foreach (['aero_plan_plans', 'aero_plan_steps', 'aero_plan_messages'] as $table) {
            $this->assertTrue(\Schema::hasTable($table), "Falta la tabla {$table}");
        }
    }

    public function testNavigationRequiresDeclaredPermissions(): void
    {
        $plugin = new \Aero\Plan\Plugin($this->app);
        $declared = array_keys($plugin->registerPermissions());

        $this->assertContains('aero.plan.use', $declared);
        $this->assertContains('aero.plan.superadmin', $declared);

        foreach ($plugin->registerNavigation() as $item) {
            foreach (($item['permissions'] ?? []) as $p) {
                $this->assertContains($p, $declared);
            }
            foreach (($item['sideMenu'] ?? []) as $side) {
                foreach (($side['permissions'] ?? []) as $p) {
                    $this->assertContains($p, $declared);
                }
            }
        }
    }
}
