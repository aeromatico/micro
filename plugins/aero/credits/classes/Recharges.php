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
     * Cotización exacta (la misma que ve el cliente en tiempo real), con
     * aritmética entera en unidades de dinero (0,0001 Bs): nunca se pierde nada.
     * Una recarga es CERRADA: se gasta el monto completo en UNA sola moneda
     * (no se combinan). Recibe floor(monto / precio) unidades enteras y lo que
     * sobra va a la billetera de Bs. Con $coin = null todo el monto va a la billetera.
     *
     * @throws \InvalidArgumentException
     */
    public static function quote(int $amount, ?string $coin = null): array
    {
        if (!in_array($amount, Settings::rechargeAmounts(), true)) {
            throw new \InvalidArgumentException('Elige uno de los montos de recarga disponibles.');
        }

        $amountUnits = Money::units($amount);
        $lines = [];
        $costUnits = 0;

        if ($coin) {
            $type = CreditType::active()->where('code', $coin)->first();
            if (!$type || $type->priceUnits() <= 0) {
                throw new \InvalidArgumentException("La moneda '{$coin}' no está disponible para recarga.");
            }

            $coins = intdiv($amountUnits, $type->priceUnits());
            if ($coins < 1) {
                throw new \InvalidArgumentException("Bs {$amount} no alcanzan para 1 moneda de {$type->label} (cuesta " . Money::price($type->priceUnits()) . "). Elige otra moneda, un monto mayor o carga el monto a tu saldo en Bs.");
            }

            $costUnits = $coins * $type->priceUnits();
            $lines[] = [
                'credit_type_id' => $type->id,
                'code'           => $type->code,
                'label'          => $type->label,
                'bob'            => (float) $amount,
                'price_bob'      => (float) $type->price_bob,
                'coins'          => $coins,
                'cost_units'     => $costUnits,
            ];
        }

        return [
            'amount'       => $amount,
            'lines'        => $lines,
            'coins'        => array_sum(array_column($lines, 'coins')),
            'wallet_units' => $amountUnits - $costUnits, // el cambio → billetera
        ];
    }

    /** Monto mínimo de una recarga: el menor de los montos ofrecidos (nunca se permite menos). */
    public static function minimumAmount(): int
    {
        return min(Settings::rechargeAmounts());
    }

    /** Tope de cordura de una cantidad libre: 10 veces el mayor monto ofrecido. */
    public static function maximumAmount(): int
    {
        return max(Settings::rechargeAmounts()) * 10;
    }

    /**
     * Compra de una cantidad EXACTA de monedas (una sola moneda). El costo es
     * monedas × precio (exacto, 4 decimales); como el QR solo admite centavos,
     * se cobra ese costo redondeado hacia ARRIBA al centavo y la diferencia
     * (siempre menos de Bs 0,01) va a la billetera, contada al 0,0001: nada se
     * pierde. Nunca se permite un pago inferior al monto mínimo de recarga.
     *
     * @throws \InvalidArgumentException
     */
    public static function quoteExact(string $coin, int $coins): array
    {
        $type = CreditType::active()->where('code', $coin)->first();
        if (!$type || $type->priceUnits() <= 0) {
            throw new \InvalidArgumentException("La moneda '{$coin}' no está disponible para recarga.");
        }

        if ($coins < 1) {
            throw new \InvalidArgumentException('Indica cuántas monedas quieres.');
        }

        $costUnits = $coins * $type->priceUnits();
        $cents = intdiv($costUnits + 99, 100);            // ceil(costUnits / 100) sin flotantes
        $amountUnits = $cents * 100;
        $min = static::minimumAmount();
        $max = static::maximumAmount();

        if ($amountUnits < Money::units($min)) {
            $needed = intdiv(Money::units($min) + $type->priceUnits() - 1, $type->priceUnits());
            throw new \InvalidArgumentException("El mínimo de recarga es Bs {$min}: de {$type->label} necesitas al menos " . number_format($needed, 0, ',', '.') . ' monedas.');
        }

        if ($amountUnits > Money::units($max)) {
            throw new \InvalidArgumentException("El máximo por recarga es Bs {$max}. Divide tu compra en varias.");
        }

        return [
            'amount'       => $cents / 100,
            'lines'        => [[
                'credit_type_id' => $type->id,
                'code'           => $type->code,
                'label'          => $type->label,
                'bob'            => $cents / 100,
                'price_bob'      => (float) $type->price_bob,
                'coins'          => $coins,
                'cost_units'     => $costUnits,
            ]],
            'coins'        => $coins,
            'wallet_units' => $amountUnits - $costUnits, // redondeo al centavo (< Bs 0,01)
        ];
    }

    /**
     * Crea la compra y emite el QR. Solo queda una compra viva por tenant:
     * al crear otra se cancela la anterior (si esa se pagara igual, se acredita igual).
     */
    public static function create(int $tenantId, int $amount, ?string $coin, ?int $userId = null): CreditPurchase
    {
        return static::issue($tenantId, static::quote($amount, $coin), $userId);
    }

    /** Compra de una cantidad exacta de monedas de UNA moneda (ver quoteExact). */
    public static function createExact(int $tenantId, string $coin, int $coins, ?int $userId = null): CreditPurchase
    {
        return static::issue($tenantId, static::quoteExact($coin, $coins), $userId);
    }

    protected static function issue(int $tenantId, array $quote, ?int $userId): CreditPurchase
    {
        $amount = $quote['amount'];

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
            'wallet_units'       => $quote['wallet_units'],
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
                tenantId: $tenantId,
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
                return false;
            }

            foreach ($p->lines as $line) {
                Credits::topUp(
                    $p->tenant_id, $line['code'], (int) $line['coins'],
                    "Recarga #{$p->id} — Bs {$line['bob']}", $p->created_by_user_id,
                    "purchase:{$p->id}:{$line['credit_type_id']}", 'purchase',
                    ['purchase_id' => $p->id, 'bob' => $line['bob'], 'price_bob' => $line['price_bob']],
                );
            }

            // Lo que no se convirtió en monedas (sobrante + no asignado) va a la billetera de Bs.
            if ((int) $p->wallet_units > 0) {
                Credits::deposit(
                    $p->tenant_id, (int) $p->wallet_units, "Recarga #{$p->id} — sobrante a tu billetera",
                    "purchase:{$p->id}:wallet", ['purchase_id' => $p->id], $p->created_by_user_id,
                );
            }

            $p->update(['status' => CreditPurchase::PAID, 'paid_at' => now()]);

            return true;
        });
    }

    /**
     * Avisa vía Aero.Notify (dependencia blanda) de una recarga acreditada o con
     * pago incompleto. Lo llama el listener del pago DESPUÉS de settle(), fuera
     * de cualquier transacción, y deja rastro en el log. Nunca rompe la
     * acreditación: si el aviso falla, solo se registra.
     *
     * @param string $event 'credits.purchase.paid' | 'credits.purchase.review'
     */
    public static function announce(string $event, CreditPurchase $p, array $extra = []): void
    {
        if (!class_exists(\Aero\Notify\Classes\Notify::class)) {
            return;
        }

        try {
            $detail = collect($p->lines)->map(fn ($l) => number_format($l['coins'], 0, ',', '.') . ' ' . str_replace('Monedas de ', '', $l['label']))->all();

            if ((int) $p->wallet_units > 0) {
                $detail[] = Money::label((int) $p->wallet_units) . ' a tu billetera';
            }

            $tenantName = class_exists(\Aero\Sites\Models\Tenant::class)
                ? \Aero\Sites\Models\Tenant::find($p->tenant_id)?->name
                : null;

            $deliveries = \Aero\Notify\Classes\Notify::fire($event, $extra + [
                'purchase_id'  => $p->id,
                'amount_bob'   => $p->amount_bob,
                'coins_detail' => implode(' · ', $detail),
                'coins_total'  => $p->totalCoins(),
                'tenant_name'  => $tenantName,
            ], ['tenant_id' => $p->tenant_id, 'dedup_key' => "{$event}:{$p->id}"]);

            \Log::info("Aero.Credits: aviso {$event} de la recarga #{$p->id} → " . count($deliveries) . ' entrega(s)');
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
