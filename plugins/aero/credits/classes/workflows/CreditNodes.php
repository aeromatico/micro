<?php namespace Aero\Credits\Classes\Workflows;

use Aero\Credits\Classes\Credits;
use Aero\Credits\Classes\Money;
use Aero\Credits\Classes\Recharges;
use Aero\Credits\Models\CreditPurchase;
use Aero\Credits\Models\CreditType;
use Aero\Credits\Models\Settings;

/**
 * Nodos de Aero.Workflows para el pago del tenant A LA PLATAFORMA con
 * monedas (se registran por `aero.workflows.registerNodes`, ver
 * Plugin::bootWorkflowsIntegration):
 *
 *   credits.balance         → saldo de monedas y billetera de Bs del tenant (solo lectura).
 *   credits.recharge        → genera el QR de una recarga (mismo flujo que Billetera → Recargar).
 *   credits.purchase_status → estado de una recarga: pagada / pendiente / vencida / anulada.
 *
 * Reglas duras (no dependen del workflow ni de la IA):
 *  - El tenant SIEMPRE es el del workflow ($tenantId); nunca uno de la entrada.
 *  - El monto de la recarga debe ser uno de los que ofrece la plataforma (Recharges::quote).
 *  - Las monedas las acredita únicamente el pago confirmado (Recharges::settle): ningún
 *    nodo acredita nada por su cuenta.
 *  - Una recarga solo se consulta si es del tenant del workflow.
 *  - Generar una recarga ANULA la que el tenant tenía pendiente (regla de Recharges).
 */
class CreditNodes
{
    public static function definitions(): array
    {
        return [
            'credits.balance' => [
                'label'    => 'Monedas › Saldo del tenant',
                'category' => 'action',
                'handler'  => [static::class, 'balance'],
                'fields'   => [
                    ['key' => 'save_as', 'label' => 'Guardar en la variable', 'type' => 'text', 'hint' => 'Por defecto «saldo»: {{ vars.saldo.text }}, .balances, .wallet_bob. Dato interno del negocio: no lo envíes a clientes.'],
                ],
            ],
            'credits.recharge' => [
                'label'    => 'Monedas › Generar recarga (QR)',
                'category' => 'action',
                'handler'  => [static::class, 'recharge'],
                'handles'  => [['id' => 'created', 'label' => 'recarga creada'], ['id' => 'failed', 'label' => 'no se pudo']],
                'note'     => 'Cobra al tenant: genera un QR a nombre de la plataforma. Las monedas llegan solo cuando el pago se confirma. Anula la recarga pendiente que el tenant tuviera.',
                'fields'   => [
                    ['key' => 'amount', 'label' => 'Monto en Bs', 'type' => 'number', 'hint' => 'Uno de los montos de recarga que ofrece la plataforma (por defecto 20, 50, 100, 200 o 1000).'],
                    ['key' => 'coin', 'label' => 'Moneda (código)', 'type' => 'text', 'hint' => 'Vacío = todo el monto va a la billetera de Bs. O el código de la moneda a comprar.'],
                    ['key' => 'save_as', 'label' => 'Guardar en la variable', 'type' => 'text', 'hint' => 'Por defecto «recarga»: {{ vars.recarga.reference }}, .image_url, .text, .expires_at'],
                ],
            ],
            'credits.purchase_status' => [
                'label'    => 'Monedas › Estado de una recarga',
                'category' => 'action',
                'handler'  => [static::class, 'purchaseStatus'],
                'handles'  => [
                    ['id' => 'paid', 'label' => 'pagada'],
                    ['id' => 'pending', 'label' => 'pendiente'],
                    ['id' => 'expired', 'label' => 'vencida o anulada'],
                    ['id' => 'review', 'label' => 'en revisión'],
                    ['id' => 'not_found', 'label' => 'no existe'],
                ],
                'fields'   => [
                    ['key' => 'reference', 'label' => 'Referencia de la recarga', 'type' => 'text', 'hint' => 'Ej: {{ vars.recarga.reference }} (la que devuelve «Generar recarga»).'],
                    ['key' => 'save_as', 'label' => 'Guardar en la variable', 'type' => 'text', 'hint' => 'Por defecto «recarga».'],
                ],
            ],
        ];
    }

    // ------------------------------------------------------------------
    // credits.balance
    // ------------------------------------------------------------------

    public static function balance(array $data, array $ctx, ?int $tenantId): array
    {
        $tenantId = static::requireTenant($tenantId);
        $balances = Credits::balances($tenantId);
        $labels = CreditType::active()->pluck('label', 'code')->all();
        $wallet = Credits::walletUnits($tenantId);

        $rows = [];
        $parts = [];

        foreach ($balances as $code => $amount) {
            $label = $labels[$code] ?? $code;
            $rows[] = ['code' => $code, 'label' => $label, 'balance' => $amount];
            $parts[] = "{$label}: {$amount}";
        }

        $parts[] = 'Billetera: ' . Money::label($wallet);

        return [
            'output' => [
                'balances'   => $rows,
                'wallet_bob' => Money::bob($wallet),
                'text'       => implode("\n", $parts),
            ],
            'var'    => static::varName($data['save_as'] ?? null, 'saldo'),
        ];
    }

    // ------------------------------------------------------------------
    // credits.recharge
    // ------------------------------------------------------------------

    public static function recharge(array $data, array $ctx, ?int $tenantId): array
    {
        $tenantId = static::requireTenant($tenantId);
        $var = static::varName($data['save_as'] ?? null, 'recarga');
        $amount = (int) preg_replace('/[^0-9]/', '', (string) ($data['amount'] ?? ''));
        $coin = trim((string) ($data['coin'] ?? '')) ?: null;

        if (!in_array($amount, Settings::rechargeAmounts(), true)) {
            return static::failed($var, 'invalid_amount', 'El monto debe ser uno de: Bs ' . implode(', Bs ', Settings::rechargeAmounts()) . '.');
        }

        try {
            $purchase = Recharges::create($tenantId, $amount, $coin);
        }
        catch (\InvalidArgumentException $e) {
            return static::failed($var, 'invalid_request', $e->getMessage());
        }
        catch (\Throwable $e) {
            // Sin cuenta de cobro de plataforma, banco caído, etc.: se informa y el flujo decide.
            return static::failed($var, 'unavailable', $e->getMessage());
        }

        return [
            'output' => static::present($purchase) + ['created' => true],
            'handle' => 'created',
            'var'    => $var,
        ];
    }

    // ------------------------------------------------------------------
    // credits.purchase_status
    // ------------------------------------------------------------------

    public static function purchaseStatus(array $data, array $ctx, ?int $tenantId): array
    {
        $tenantId = static::requireTenant($tenantId);
        $var = static::varName($data['save_as'] ?? null, 'recarga');
        $reference = trim((string) ($data['reference'] ?? ''));

        $purchase = $reference === '' ? null : CreditPurchase::where('tenant_id', $tenantId)
            ->where(fn ($q) => ctype_digit($reference) ? $q->where('id', (int) $reference) : $q->where('payment_reference', $reference))
            ->first();

        if (!$purchase) {
            return ['output' => ['found' => false, 'reason' => 'No hay una recarga de este tenant con esa referencia.'], 'handle' => 'not_found', 'var' => $var];
        }

        // Una recarga pendiente cuyo plazo ya pasó está vencida aunque el barrido aún no la marque.
        $status = $purchase->status;

        if ($status === CreditPurchase::PENDING && $purchase->expires_at && $purchase->expires_at->isPast()) {
            $status = CreditPurchase::EXPIRED;
        }

        $handle = match ($status) {
            CreditPurchase::PAID    => 'paid',
            CreditPurchase::PENDING => 'pending',
            CreditPurchase::REVIEW  => 'review',
            default                 => 'expired',
        };

        return ['output' => static::present($purchase, $status) + ['found' => true], 'handle' => $handle, 'var' => $var];
    }

    // ------------------------------------------------------------------
    // Utilidades
    // ------------------------------------------------------------------

    protected static function present(CreditPurchase $p, ?string $status = null): array
    {
        $qr = $p->qr_code_id && class_exists(\Aero\Pay\Models\QrCode::class) ? \Aero\Pay\Models\QrCode::find($p->qr_code_id) : null;
        $imageUrl = $qr && $qr->qr_image ? url('/api/v1/pay/public/qr/' . $qr->internal_reference . '/image') : null;
        $amount = number_format((float) $p->amount_bob, 2, '.', '');

        return [
            'id'         => $p->id,
            'reference'  => $p->payment_reference,
            'status'     => $status ?? $p->status,
            'amount_bob' => (float) $p->amount_bob,
            'coins'      => $p->totalCoins(),
            'expires_at' => $p->expires_at?->toIso8601String(),
            'paid_at'    => $p->paid_at?->toIso8601String(),
            'image_url'  => $imageUrl,
            'text'       => "Recarga de Bs {$amount}" . ($p->totalCoins() ? " ({$p->totalCoins()} monedas)" : '')
                . ($p->expires_at && ($status ?? $p->status) === CreditPurchase::PENDING ? ' — paga antes de ' . $p->expires_at->format('H:i') : '')
                . ($imageUrl ? "\n" . $imageUrl : ''),
        ];
    }

    protected static function failed(string $var, string $code, string $reason): array
    {
        return ['output' => ['created' => false, 'error' => $code, 'reason' => $reason], 'handle' => 'failed', 'var' => $var];
    }

    protected static function varName(mixed $name, string $default): string
    {
        $name = preg_replace('/[^a-z0-9_]/i', '', (string) $name);

        return $name !== '' && $name !== null ? $name : $default;
    }

    protected static function requireTenant(?int $tenantId): int
    {
        if (!$tenantId) {
            throw new \RuntimeException('Los nodos de monedas necesitan un workflow con cuenta (tenant).');
        }

        return $tenantId;
    }
}
