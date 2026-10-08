<?php namespace Aero\Sites\Classes\Workflows;

use Aero\Sites\Models\PlanRenewal;
use Aero\Sites\Models\Tenant;

/**
 * Nodo de Aero.Workflows con el estado de la suscripción del tenant a la
 * plataforma (se registra por `aero.workflows.registerNodes`, ver
 * Plugin::bootWorkflowsIntegration):
 *
 *   sites.plan_status → plan, vencimiento, días restantes y el cobro de
 *                       renovación pendiente (con su QR) si ya se generó.
 *
 * Solo lectura: el cobro de la renovación lo genera el proceso
 * `aero.sites:generate-renewals` y se confirma por el puente de Aero.Pay; este
 * nodo nunca crea ni confirma un cobro. El tenant es SIEMPRE el del workflow.
 */
class PlanNodes
{
    /** Días antes del vencimiento en que se considera «por vencer». */
    public const EXPIRING_DAYS = 7;

    public static function definitions(): array
    {
        return [
            'sites.plan_status' => [
                'label'    => 'Suscripción › Estado del plan del tenant',
                'category' => 'action',
                'handler'  => [static::class, 'status'],
                'handles'  => [
                    ['id' => 'active', 'label' => 'vigente'],
                    ['id' => 'expiring', 'label' => 'por vencer'],
                    ['id' => 'overdue', 'label' => 'vencido o suspendido'],
                    ['id' => 'no_plan', 'label' => 'sin plan'],
                ],
                'fields'   => [
                    ['key' => 'save_as', 'label' => 'Guardar en la variable', 'type' => 'text', 'hint' => 'Por defecto «plan»: {{ vars.plan.plan_name }}, .expires_at, .days_left, .text, .renewal_amount, .renewal_image_url. Dato interno del negocio: no lo envíes a clientes.'],
                ],
            ],
        ];
    }

    public static function status(array $data, array $ctx, ?int $tenantId): array
    {
        if (!$tenantId) {
            throw new \RuntimeException('El nodo de suscripción necesita un workflow con cuenta (tenant).');
        }

        $var = preg_replace('/[^a-z0-9_]/i', '', (string) ($data['save_as'] ?? '')) ?: 'plan';
        $tenant = Tenant::with('plan')->find($tenantId);

        if (!$tenant || !$tenant->plan) {
            return ['output' => ['has_plan' => false, 'text' => 'Este tenant no tiene un plan asignado.'], 'handle' => 'no_plan', 'var' => $var];
        }

        $expires = $tenant->plan_expires_at;
        $daysLeft = $expires ? (int) now()->startOfDay()->diffInDays($expires->copy()->startOfDay(), false) : null;
        $suspended = in_array($tenant->status, ['suspended', 'pending_payment'], true);

        $handle = match (true) {
            $suspended || ($daysLeft !== null && $daysLeft < 0)          => 'overdue',
            $daysLeft !== null && $daysLeft <= static::EXPIRING_DAYS      => 'expiring',
            default                                                       => 'active',
        };

        $output = [
            'has_plan'       => true,
            'plan_name'      => $tenant->plan->name,
            'status'         => $tenant->status,
            'billing_period' => $tenant->billing_period,
            'expires_at'     => $expires?->toDateString(),
            'days_left'      => $daysLeft,
        ] + static::renewal($tenantId);

        $output['text'] = static::text($output, $handle);

        return ['output' => $output, 'handle' => $handle, 'var' => $var];
    }

    /** El cobro de renovación pendiente más próximo, con la imagen de su QR. */
    protected static function renewal(int $tenantId): array
    {
        $renewal = PlanRenewal::pending()->where('tenant_id', $tenantId)->orderBy('cycle_due_at')->first();

        if (!$renewal) {
            return ['renewal_pending' => false];
        }

        $qr = $renewal->qr_code_id && class_exists(\Aero\Pay\Models\QrCode::class) ? \Aero\Pay\Models\QrCode::find($renewal->qr_code_id) : null;

        return [
            'renewal_pending'   => true,
            'renewal_amount'    => (float) $renewal->amount,
            'renewal_currency'  => $renewal->currency,
            'renewal_due_at'    => $renewal->cycle_due_at?->toDateString(),
            'renewal_reference' => $renewal->payment_reference,
            'renewal_image_url' => $qr && $qr->qr_image ? url('/api/v1/pay/public/qr/' . $qr->internal_reference . '/image') : null,
        ];
    }

    protected static function text(array $o, string $handle): string
    {
        $line = match ($handle) {
            'overdue'  => "Tu plan {$o['plan_name']} está vencido o suspendido.",
            'expiring' => "Tu plan {$o['plan_name']} vence en {$o['days_left']} día(s) ({$o['expires_at']}).",
            default    => "Tu plan {$o['plan_name']} está vigente" . ($o['expires_at'] ? " hasta el {$o['expires_at']}." : '.'),
        };

        if ($o['renewal_pending']) {
            $line .= "\nRenovación pendiente: " . number_format($o['renewal_amount'], 2, '.', '') . " {$o['renewal_currency']}"
                . ($o['renewal_image_url'] ? "\n" . $o['renewal_image_url'] : '');
        }

        return $line;
    }
}
