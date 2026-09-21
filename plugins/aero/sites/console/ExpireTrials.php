<?php namespace Aero\Sites\Console;

use Aero\Sites\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Suspende los tenants cuya prueba gratis ya venció. Solo las pruebas: los
 * planes mensual/anual guardan plan_expires_at pero no se suspenden solos
 * (aún no hay flujo de renovación; suspender a quien pagó sería un bloqueo
 * sin salida).
 */
class ExpireTrials extends Command
{
    protected $signature = 'aero.sites:expire-trials';

    protected $description = 'Suspende los tenants con prueba gratis vencida.';

    public function handle(): int
    {
        $expired = Tenant::where('status', 'active')
            ->where('billing_period', 'trial')
            ->where('plan_expires_at', '<', now())
            ->get();

        foreach ($expired as $tenant) {
            $this->info("Prueba vencida: \"{$tenant->handle}\" (venció " . $tenant->plan_expires_at->diffForHumans() . ')');
            $tenant->status = 'suspended';
            $tenant->save();
        }

        $this->info($expired->count() . ' prueba(s) suspendida(s).');

        return self::SUCCESS;
    }
}
