<?php namespace Aero\Sites\Console;

use Aero\Sites\Models\PlanRenewal;
use Aero\Sites\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Vence las renovaciones pendientes que pasaron su fecha de cobro y
 * suspende al tenant tras 5 días de gracia sin pagar. Espejo de
 * ExpireTrials, pero para tenants mensual/anual en vez de prueba gratis.
 */
class ExpireRenewals extends Command
{
    protected $signature = 'aero.sites:expire-renewals';
    protected $description = 'Vence las renovaciones no pagadas y suspende al tenant tras el periodo de gracia.';

    /** Días de gracia tras el vencimiento antes de suspender. */
    protected const GRACE_DAYS = 5;

    public function handle(): int
    {
        $overdue = PlanRenewal::pending()
            ->where('cycle_due_at', '<', now()->subDays(self::GRACE_DAYS))
            ->get();

        foreach ($overdue as $renewal) {
            $renewal->status = 'expired';
            $renewal->save();
            $this->info("Renovación vencida: tenant #{$renewal->tenant_id}, ciclo " . $renewal->cycle_due_at->toFormattedDateString());
        }

        $suspended = Tenant::where('status', 'active')
            ->whereIn('billing_period', ['monthly', 'annual'])
            ->where('plan_expires_at', '<', now()->subDays(self::GRACE_DAYS))
            ->get();

        foreach ($suspended as $tenant) {
            $this->info("Sin pago tras la gracia: \"{$tenant->handle}\" (venció " . $tenant->plan_expires_at->diffForHumans() . ')');
            $tenant->status = 'suspended';
            $tenant->save();
        }

        $this->info($overdue->count() . ' renovación(es) vencida(s), ' . $suspended->count() . ' tenant(s) suspendido(s).');
        return self::SUCCESS;
    }
}
