<?php namespace Aero\Sites\Console;

use Aero\Sites\Classes\PlanStatus;
use Aero\Sites\Classes\RenewalIssuer;
use Aero\Sites\Models\PlanRenewal;
use Aero\Sites\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Genera el cobro QR de renovación para tenants mensual/anual cuyo
 * plan_expires_at cae dentro de la ventana de generación (RenewalIssuer usa la
 * cuenta bancaria de plataforma que cobra el alta). El pago se confirma
 * después, de forma asíncrona, en Plugin::bootPayRenewalBridge(). Al emitirlo
 * avisa al tenant (sites.tenant.renewal_due) con el enlace a «Mi plan».
 */
class GenerateRenewals extends Command
{
    protected $signature = 'aero.sites:generate-renewals';
    protected $description = 'Genera el cobro QR de renovación para tenants mensual/anual próximos a vencer.';

    public function handle(): int
    {
        if (!class_exists(\Aero\Pay\Classes\QrIssuer::class)) {
            $this->warn('Aero.Pay no está instalado: no se puede generar ningún cobro de renovación.');
            return self::SUCCESS;
        }

        $tenants = Tenant::where('status', 'active')
            ->whereIn('billing_period', ['monthly', 'annual'])
            ->whereNotNull('plan_expires_at')
            ->where('plan_expires_at', '<=', now()->addDays(PlanStatus::WARN_DAYS))
            ->get();

        $generated = 0;

        foreach ($tenants as $tenant) {
            // Ya emitido (o ya pagado) este ciclo: el recordatorio se encarga del aviso.
            if (PlanRenewal::where('tenant_id', $tenant->id)->where('cycle_due_at', $tenant->plan_expires_at)->exists()) {
                continue;
            }

            if (!$tenant->plan) {
                $this->warn("Tenant \"{$tenant->handle}\" sin plan asignado: se omite.");
                continue;
            }

            $renewal = RenewalIssuer::issue($tenant);
            if (!$renewal) {
                $this->warn("No se pudo emitir la renovación de \"{$tenant->handle}\" (¿falta la cuenta bancaria de alta en Sites → Configuración?).");
                continue;
            }

            $this->info("Renovación generada: \"{$tenant->handle}\" — Bs {$renewal->amount} (vence " . $tenant->plan_expires_at->toFormattedDateString() . ')');
            $generated++;

            static::notify($tenant, $renewal);
        }

        $this->info("{$generated} renovación(es) generada(s).");
        return self::SUCCESS;
    }

    public static function notify(Tenant $tenant, PlanRenewal $renewal): void
    {
        if (!class_exists(\Aero\Notify\Classes\Notify::class)) {
            return;
        }

        try {
            \Aero\Notify\Classes\Notify::fire('sites.tenant.renewal_due', [
                'tenant_name' => $tenant->name,
                'amount'      => $renewal->amount,
                'due_date'    => $tenant->plan_expires_at->format('d/m/Y'),
                'pay_url'     => \Backend::url('aero/sites/myplan'),
            ], ['tenant_id' => $tenant->id]);
        }
        catch (\Throwable $e) {
            \Log::error('Aero.Sites: fallo notificando sites.tenant.renewal_due: ' . $e->getMessage());
        }
    }
}
