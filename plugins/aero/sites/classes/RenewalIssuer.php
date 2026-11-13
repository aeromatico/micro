<?php namespace Aero\Sites\Classes;

use Aero\Sites\Models\PlanRenewal;
use Aero\Sites\Models\Settings;
use Aero\Sites\Models\Tenant;

/**
 * Emite (o recupera) el cobro QR de renovación del ciclo vigente de un
 * tenant. Lo usan el comando diario (aero.sites:generate-renewals), los
 * recordatorios y la pantalla «Mi plan» (botón «Renovar ahora»), para que
 * haya un único QR por ciclo y un único camino de emisión.
 */
class RenewalIssuer
{
    /** Cobro pendiente del ciclo actual, si ya se emitió. */
    public static function current(Tenant $tenant): ?PlanRenewal
    {
        if (!$tenant->plan_expires_at) {
            return null;
        }

        return PlanRenewal::pending()
            ->where('tenant_id', $tenant->id)
            ->where('cycle_due_at', $tenant->plan_expires_at)
            ->latest('id')
            ->first();
    }

    /**
     * @return PlanRenewal|null null si no aplica o no se pudo emitir (se registra el motivo)
     */
    public static function issue(Tenant $tenant): ?PlanRenewal
    {
        if (!in_array($tenant->billing_period, ['monthly', 'annual'], true) || !$tenant->plan_expires_at) {
            return null;
        }

        if ($existing = static::current($tenant)) {
            return $existing;
        }

        // Ya pagado este ciclo: no hay nada que cobrar.
        if (PlanRenewal::where('tenant_id', $tenant->id)->where('cycle_due_at', $tenant->plan_expires_at)->exists()) {
            return null;
        }

        $plan = $tenant->plan;
        $bankAccount = Settings::getSignupBankAccount();

        if (!$plan || !$bankAccount || !class_exists(\Aero\Pay\Classes\QrIssuer::class)) {
            return null;
        }

        $amount = $plan->renewalPrice($tenant->billing_period);

        try {
            $qrCode = app(\Aero\Pay\Classes\QrIssuer::class)->issue(
                bankAccount: $bankAccount,
                amount: $amount,
                currency: 'BOB',
                description: "Renovación Market — {$tenant->handle} (plan {$plan->name})",
                origin: 'sites',
                dueDate: $tenant->plan_expires_at->toDateString(),
                tenantId: $tenant->id,
            );
        }
        catch (\Throwable $e) {
            \Log::error("Aero\\Sites: fallo emitiendo QR de renovación para el tenant {$tenant->id}: " . $e->getMessage());
            return null;
        }

        return PlanRenewal::create([
            'tenant_id'         => $tenant->id,
            'plan_id'           => $plan->id,
            'period'            => $tenant->billing_period,
            'cycle_due_at'      => $tenant->plan_expires_at,
            'status'            => 'pending',
            'qr_code_id'        => $qrCode->id,
            'payment_reference' => $qrCode->internal_reference,
            'amount'            => $amount,
            'currency'          => 'BOB',
        ]);
    }
}
