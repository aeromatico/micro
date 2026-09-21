<?php namespace Aero\Credits\Classes;

use Aero\Credits\Models\CreditPurchase;
use Aero\Credits\Models\CreditType;
use Aero\Credits\Models\Settings;
use DB;

/**
 * Recarga de monedas por el cliente: elige un monto en Bs, decide en qué
 * monedas gastarlo, paga un QR y, al confirmarse el pago, recibe las monedas
 * (Credits::topUp con kind 'purchase', idempotente por compra y moneda).
 */
class Recharges
{
    /** Monedas que se pueden comprar (activas y con precio). */
    public static function catalog(): array
    {
        return CreditType::active()->get()
            ->filter(fn ($t) => (float) $t->price_bob > 0)
            ->map(fn ($t) => [
                'id'    => $t->id, 'code' => $t->code, 'label' => $t->label, 'color' => $t->color,
                'price' => (float) $t->price_bob, 'exchangeable' => (bool) $t->is_exchangeable,
            ])->values()->all();
    }

    /**
     * Cotización exacta (la misma que ve el cliente en tiempo real):
     * monedas = floor(Bs / precio). El sobrante por redondeo se informa.
     *
     * @param array<string,int|float> $allocations code => Bs asignados a esa moneda
     * @throws \InvalidArgumentException
     */
    public static function quote(int $amount, array $allocations): array
    {
        if (!in_array($amount, Settings::rechargeAmounts(), true)) {
            throw new \InvalidArgumentException('Elige uno de los montos de recarga disponibles.');
        }

        $types = CreditType::active()->get()->keyBy('code');
        $lines = [];
        $sum = 0.0;

        foreach ($allocations as $code => $bob) {
            $bob = round((float) $bob, 2);

            if ($bob <= 0) {
                continue;
            }

            $type = $types[$code] ?? null;
            if (!$type || (float) $type->price_bob <= 0) {
                throw new \InvalidArgumentException("La moneda '{$code}' no está disponible para recarga.");
            }

            $coins = (int) floor($bob / (float) $type->price_bob + 1e-9);
            if ($coins < 1) {
                throw new \InvalidArgumentException("Bs {$bob} no alcanzan para 1 moneda de {$type->label} (cuesta Bs {$type->price_bob}).");
            }

            $lines[] = [
                'credit_type_id' => $type->id,
                'code'           => $type->code,
                'label'          => $type->label,
                'bob'            => $bob,
                'price_bob'      => (float) $type->price_bob,
                'coins'          => $coins,
                'rounding_bob'   => round($bob - $coins * (float) $type->price_bob, 4),
            ];
            $sum += $bob;
        }

        if (!$lines) {
            throw new \InvalidArgumentException('Elige al menos una moneda.');
        }

        if (abs($sum - $amount) > 0.001) {
            throw new \InvalidArgumentException("El reparto suma Bs {$sum} y debe sumar exactamente Bs {$amount}.");
        }

        return ['amount' => $amount, 'lines' => $lines, 'coins' => array_sum(array_column($lines, 'coins'))];
    }

    /**
     * Crea la compra y emite el QR. Solo queda una compra viva por tenant:
     * al crear otra se cancela la anterior (si esa se pagara igual, se acredita igual).
     */
    public static function create(int $tenantId, int $amount, array $allocations, ?int $userId = null): CreditPurchase
    {
        $quote = static::quote($amount, $allocations);

        $bank = Settings::purchaseBankAccount();
        if (!$bank || !class_exists(\Aero\Pay\Classes\QrIssuer::class)) {
            throw new \RuntimeException('Las recargas no están disponibles en este momento. Contacta a soporte.');
        }

        foreach (CreditPurchase::where('tenant_id', $tenantId)->where('status', CreditPurchase::PENDING)->get() as $old) {
            static::cancel($old);
        }

        $purchase = CreditPurchase::create([
            'tenant_id'          => $tenantId,
            'amount_bob'         => $amount,
            'status'             => CreditPurchase::PENDING,
            'lines'              => $quote['lines'],
            'expires_at'         => now()->addMinutes(Settings::purchaseTtlMinutes()),
            'created_by_user_id' => $userId,
        ]);

        try {
            $qr = app(\Aero\Pay\Classes\QrIssuer::class)->issue(
                bankAccount: $bank,
                amount: (float) $amount,
                currency: 'BOB',
                description: "Recarga de monedas #{$purchase->id} — tenant {$tenantId}",
                origin: 'credits',
            );
        }
        catch (\Throwable $e) {
            $purchase->delete();
            throw $e;
        }

        $purchase->update(['qr_code_id' => $qr->id, 'payment_reference' => $qr->internal_reference]);

        return $purchase;
    }

    /**
     * Acredita las monedas de una compra pagada. Idempotente: llamarlo dos
     * veces (webhook + polling) acredita una sola vez, por el lock de la
     * compra y por la clave de idempotencia de cada movimiento.
     */
    public static function settle(CreditPurchase $purchase, ?float $paidAmount = null): bool
    {
        return DB::transaction(function () use ($purchase, $paidAmount) {
            $p = CreditPurchase::where('id', $purchase->id)->lockForUpdate()->first();

            if (!$p || $p->status === CreditPurchase::PAID) {
                return false;
            }

            if ($paidAmount !== null && $paidAmount + 0.005 < (float) $p->amount_bob) {
                $p->update(['status' => CreditPurchase::REVIEW]);
                \Log::error("Aero.Credits: pago de Bs {$paidAmount} menor al monto de la recarga #{$p->id} (Bs {$p->amount_bob}); requiere revisión manual.");
                DB::afterCommit(fn () => static::notify('credits.purchase.review', $p, ['paid_bob' => $paidAmount]));
                return false;
            }

            foreach ($p->lines as $line) {
                Credits::topUp(
                    $p->tenant_id, $line['code'], (int) $line['coins'],
                    "Recarga #{$p->id} — Bs {$line['bob']}", $p->created_by_user_id,
                    "purchase:{$p->id}:{$line['credit_type_id']}", 'purchase',
                    ['purchase_id' => $p->id, 'bob' => $line['bob'], 'price_bob' => $line['price_bob'], 'rounding_bob' => $line['rounding_bob'] ?? 0],
                );
            }

            $p->update(['status' => CreditPurchase::PAID, 'paid_at' => now()]);

            DB::afterCommit(fn () => static::notify('credits.purchase.paid', $p));

            return true;
        });
    }

    /** Aviso vía Aero.Notify (dependencia blanda). Nunca rompe la acreditación si el aviso falla. */
    protected static function notify(string $event, CreditPurchase $p, array $extra = []): void
    {
        if (!class_exists(\Aero\Notify\Classes\Notify::class)) {
            return;
        }

        try {
            $detail = collect($p->lines)->map(fn ($l) => number_format($l['coins'], 0, ',', '.') . ' ' . str_replace('Monedas de ', '', $l['label']))->implode(' · ');

            \Aero\Notify\Classes\Notify::fire($event, $extra + [
                'purchase_id'  => $p->id,
                'amount_bob'   => $p->amount_bob,
                'coins_detail' => $detail,
                'coins_total'  => $p->totalCoins(),
            ], ['tenant_id' => $p->tenant_id, 'dedup_key' => "{$event}:{$p->id}"]);
        }
        catch (\Throwable $e) {
            \Log::error("Aero.Credits: no se pudo enviar el aviso {$event} de la recarga #{$p->id}: " . $e->getMessage());
        }
    }

    public static function cancel(CreditPurchase $purchase, string $status = CreditPurchase::CANCELLED): void
    {
        if ($purchase->status !== CreditPurchase::PENDING) {
            return;
        }

        $purchase->update(['status' => $status]);

        if ($purchase->qr_code_id && class_exists(\Aero\Pay\Models\QrCode::class)) {
            $qr = \Aero\Pay\Models\QrCode::find($purchase->qr_code_id);

            if ($qr && $qr->status === 'pending') {
                try {
                    app(\Aero\Pay\Classes\PaymentDriverManager::class)->make($qr->bank_code)->cancelQr($qr->bankAccount, $qr->external_qr_id);
                }
                catch (\Throwable $e) {
                    \Log::info("Aero.Credits: no se pudo anular el QR #{$qr->id} contra el banco (se marca cancelado igual): " . $e->getMessage());
                }

                $qr->update(['status' => 'cancelled']);
            }
        }
    }

    /** Marca como vencidas las compras cuyo QR no se pagó a tiempo. */
    public static function expirePending(): int
    {
        $n = 0;

        foreach (CreditPurchase::expirable()->limit(200)->get() as $purchase) {
            static::cancel($purchase, CreditPurchase::EXPIRED);
            $n++;
        }

        return $n;
    }
}
