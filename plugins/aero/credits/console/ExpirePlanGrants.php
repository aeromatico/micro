<?php namespace Aero\Credits\Console;

use Aero\Credits\Classes\Credits;
use Aero\Credits\Models\CreditPlanGrantLot;
use Illuminate\Console\Command;

/**
 * Vence los créditos de regalo de plan (mode=expiring, ver
 * Aero\Sites\Classes\PlanCredits) que quedaron sin usar al terminar su ciclo.
 */
class ExpirePlanGrants extends Command
{
    protected $signature = 'credits:expire-plan-grants';

    protected $description = 'Vence los créditos de regalo de plan que no se usaron durante el ciclo.';

    public function handle(): int
    {
        $lots = CreditPlanGrantLot::whereNull('expired_at')
            ->where('expires_at', '<', now())
            ->with('creditType')
            ->limit(500)
            ->get();

        $expired = 0;
        $totalAmount = 0;

        foreach ($lots as $lot) {
            if ($lot->remaining_amount > 0 && $lot->creditType) {
                $tx = Credits::expire(
                    $lot->tenant_id,
                    $lot->creditType->code,
                    $lot->remaining_amount,
                    "Vencimiento de créditos de regalo del plan (lote #{$lot->id})",
                    "plan_grant_expiry:lot:{$lot->id}"
                );

                if ($tx) {
                    $totalAmount += abs($tx->delta);
                }
            }

            $lot->remaining_amount = 0;
            $lot->expired_at = now();
            $lot->save();
            $expired++;
        }

        $this->info("{$expired} lote(s) vencido(s), {$totalAmount} crédito(s) expirado(s) en total.");

        return self::SUCCESS;
    }
}
