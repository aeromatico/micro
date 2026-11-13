<?php namespace Aero\Sites\Tests;

use Aero\Sites\Classes\PlanStatus;
use Aero\Sites\Models\Tenant;
use Carbon\Carbon;
use PluginTestCase;

class PlanStatusTest extends PluginTestCase
{
    protected $autoRegister = false;

    protected function migrateCurrentPlugin()
    {
    }

    protected function tenant(string $period, ?Carbon $expires): Tenant
    {
        $t = new Tenant;
        $t->billing_period = $period;
        $t->plan_expires_at = $expires;

        return $t;
    }

    public function testEstadosSegunDiasRestantes(): void
    {
        Carbon::setTestNow('2026-11-13 15:00:00');

        $cases = [
            [10, 'ok'], [7, 'soon'], [3, 'soon'], [1, 'soon'], [0, 'soon'], [-1, 'overdue'], [-4, 'overdue'],
        ];

        foreach ($cases as [$days, $state]) {
            $s = PlanStatus::for($this->tenant('monthly', now()->addDays($days)->setTime(23, 0)));
            $this->assertSame($state, $s['state'], "días={$days}");
            $this->assertSame($days, $s['days'], "días={$days}");
        }

        Carbon::setTestNow();
    }

    public function testPruebaYSinVencimientoNoMuestranNada(): void
    {
        $this->assertSame('none', PlanStatus::for($this->tenant('trial', now()->addDay()))['state']);
        $this->assertSame('none', PlanStatus::for($this->tenant('monthly', null))['state']);
    }

    public function testFraseYGracia(): void
    {
        Carbon::setTestNow('2026-11-13 10:00:00');

        $this->assertSame('vence hoy', PlanStatus::phrase(PlanStatus::for($this->tenant('annual', now()))));
        $this->assertSame('vence mañana', PlanStatus::phrase(PlanStatus::for($this->tenant('annual', now()->addDay()))));
        $this->assertSame('venció hace 2 días', PlanStatus::phrase(PlanStatus::for($this->tenant('annual', now()->subDays(2)))));
        $this->assertSame(3, PlanStatus::for($this->tenant('annual', now()->subDays(2)))['grace_left']);

        Carbon::setTestNow();
    }
}
