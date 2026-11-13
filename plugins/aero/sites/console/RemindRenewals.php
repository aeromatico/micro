<?php namespace Aero\Sites\Console;

use Aero\Sites\Classes\PlanStatus;
use Aero\Sites\Classes\RenewalIssuer;
use Aero\Sites\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Recordatorios por correo de una renovación sin pagar: 3 días antes, 1 día
 * antes, el día del vencimiento y 2 días después (dentro de la gracia). Una
 * sola vez por tenant, ciclo y hito. Si el QR del ciclo aún no existe lo emite.
 */
class RemindRenewals extends Command
{
    protected $signature = 'aero.sites:remind-renewals {--dry : Solo muestra a quién avisaría}';
    protected $description = 'Envía los recordatorios de renovación (3 y 1 día antes, el día y 2 días después).';

    /** Días respecto al vencimiento en que se avisa (negativo = ya venció). */
    protected const MILESTONES = [3, 1, 0, -2];

    public function handle(): int
    {
        if (!class_exists(\Aero\Notify\Classes\Notify::class)) {
            $this->warn('Aero.Notify no está instalado: no hay a quién avisar.');
            return self::SUCCESS;
        }

        $sent = 0;

        $tenants = Tenant::where('status', 'active')
            ->whereIn('billing_period', ['monthly', 'annual'])
            ->whereNotNull('plan_expires_at')
            ->get();

        foreach ($tenants as $tenant) {
            $status = PlanStatus::for($tenant);

            if (!in_array($status['days'], self::MILESTONES, true)) {
                continue;
            }

            $key = "sites:renewal-reminder:{$tenant->id}:{$tenant->plan_expires_at->toDateString()}:{$status['days']}";
            if (Cache::has($key)) {
                continue;
            }

            $renewal = RenewalIssuer::issue($tenant);
            if (!$renewal) {
                // Sin cobro pendiente (ya pagado o sin cuenta de alta): nada que recordar.
                continue;
            }

            if ($this->option('dry')) {
                $this->line("[dry] {$tenant->handle}: {$status['days']} día(s) — Bs {$renewal->amount}");
                continue;
            }

            try {
                \Aero\Notify\Classes\Notify::fire('sites.tenant.renewal_reminder', [
                    'tenant_name' => $tenant->name,
                    'amount'      => $renewal->amount,
                    'due_date'    => $tenant->plan_expires_at->format('d/m/Y'),
                    'when'        => PlanStatus::phrase($status),
                    'overdue'     => $status['days'] < 0 ? '1' : '',
                    'grace_days'  => (string) ($status['grace_left'] ?? ''),
                    'pay_url'     => \Backend::url('aero/sites/myplan'),
                ], ['tenant_id' => $tenant->id]);

                Cache::put($key, 1, now()->addDays(10));
                $sent++;
                $this->info("Recordatorio: \"{$tenant->handle}\" ({$status['days']} día(s)).");
            }
            catch (\Throwable $e) {
                \Log::error('Aero.Sites: fallo notificando sites.tenant.renewal_reminder: ' . $e->getMessage());
            }
        }

        $this->info("{$sent} recordatorio(s) enviado(s).");
        return self::SUCCESS;
    }
}
