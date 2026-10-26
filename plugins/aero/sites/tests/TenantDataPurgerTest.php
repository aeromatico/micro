<?php namespace Aero\Sites\Tests;

use Aero\Sites\Classes\TenantDataPurger;
use PluginTestCase;

class TenantDataPurgerTest extends PluginTestCase
{
    protected $autoRegister = false;

    protected function migrateCurrentPlugin()
    {
    }

    public function testBorraSoloLasFilasDelTenantIndicado(): void
    {
        foreach (['aero_demo_a', 'aero_demo_b'] as $t) {
            \Schema::create($t, function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
            });
            \DB::table($t)->insert([['tenant_id' => 1], ['tenant_id' => 2], ['tenant_id' => 2]]);
        }

        $dry = TenantDataPurger::purge(2, true);
        $this->assertSame(2, $dry['aero_demo_a']);
        $this->assertSame(3, \DB::table('aero_demo_a')->count(), 'la simulación no borra');

        TenantDataPurger::purge(2);

        $this->assertSame(1, \DB::table('aero_demo_a')->count());
        $this->assertSame(1, \DB::table('aero_demo_b')->where('tenant_id', 1)->count());
        $this->assertSame(0, \DB::table('aero_demo_b')->where('tenant_id', 2)->count());
    }

    public function testRechazaTenantInvalido(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TenantDataPurger::purge(0);
    }
}
