<?php namespace Aero\Sites\Console;

use Aero\Sites\Models\PlanRenewal;
use Aero\Sites\Models\Settings;
use Aero\Sites\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Genera el cobro QR de renovación para tenants mensual/anual cuyo
 * plan_expires_at cae dentro de la ventana de generación, reutilizando la
 * misma cuenta bancaria de plataforma que cobra el alta (Settings::
 * getSignupBankAccount()). El pago se confirma después, de forma
 * asíncrona, en Plugin::bootPayRenewalBridge().
 */
class GenerateRenewals extends Command
{
    protected $signature = 'aero.sites:generate-renewals';
    protected $description = 'Genera el cobro QR de renovación para tenants mensual/anual próximos a vencer.';

    /** Días antes del vencimiento en que se genera el cobro. */
    protected const WINDOW_DAYS = 5;

    public function handle(): int
    {
        if (!class_exists(\Aero\Pay\Classes\QrIssuer::class)) {
            $this->warn('Aero.Pay no está instalado: no se puede generar ningún cobro de renovación.');
            return self::SUCCESS;
        }

        $bankAccount = Settings::getSignupBankAccount();
        if (!$bankAccount) {
            $this->warn('No hay cuenta bancaria de alta configurada (Sites → Configuración): no se generó ningún cobro.');
            return self::SUCCESS;
        }

        $tenants = Tenant::where('status', 'active')
            ->whereIn('billing_period', ['monthly', 'annual'])
            ->whereNotNull('plan_expires_at')
            ->where('plan_expires_at', '<=', now()->addDays(self::WINDOW_DAYS))
            ->get();

        $generated = 0;

        foreach ($tenants as $tenant) {
            if (PlanRenewal::where('tenant_id', $tenant->id)->where('cycle_due_at', $tenant->plan_expires_at)->exists()) {
                continue;
            }

            $plan = $tenant->plan;
            if (!$plan) {
                $this->warn("Tenant \"{$tenant->handle}\" sin plan asignado: se omite.");
                continue;
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
                );
            } catch (\Throwable $e) {
                \Log::error("Aero\\Sites: fallo emitiendo QR de renovación para el tenant {$tenant->id}: " . $e->getMessage());
                continue;
            }

            $renewal = PlanRenewal::create([
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

            $this->info("Renovación generada: \"{$tenant->handle}\" — Bs {$amount} (vence " . $tenant->plan_expires_at->toFormattedDateString() . ')');
            $generated++;

            if (class_exists(\Aero\Notify\Classes\Notify::class)) {
                try {
                    \Aero\Notify\Classes\Notify::fire('sites.tenant.renewal_due', [
                        'tenant_name' => $tenant->name,
                        'amount'      => $amount,
                        'due_date'    => $tenant->plan_expires_at->toFormattedDateString(),
                    ], ['tenant_id' => $tenant->id]);
                } catch (\Throwable $e) {
                    \Log::error('Aero.Sites: fallo notificando sites.tenant.renewal_due: ' . $e->getMessage());
                }
            }
        }

        $this->info("{$generated} renovación(es) generada(s).");
        return self::SUCCESS;
    }
}
