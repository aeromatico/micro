<?php namespace Aero\Pos\Classes;

use Aero\Pos\Models\Payment;
use Aero\Pos\Models\PaymentMethod;
use Aero\Pos\Models\PosSettings;
use Aero\Pos\Models\PosTable;
use Aero\Pos\Models\Sale;
use Aero\Pos\Models\Shift;
use Aero\Shop\Classes\Exceptions\OrderException;
use Aero\Shop\Classes\OrderService;
use Aero\Shop\Models\ShopSettings;
use Db;

/**
 * Crea una venta de mostrador: pedido real de Aero.Shop (precios del catálogo, stock,
 * numeración) + pagos del POS + turno. Idempotente por `client_uuid`: reintentar una
 * venta hecha sin conexión devuelve la misma venta.
 */
class SaleService
{
    /**
     * @param array $data ['client_uuid','items'=>[['product_id','variant_id'?,'quantity']],'discount_percent'?,
     *                     'nit'?,'tax_name'?,'table_id'?,'payments'=>[['method_id','amount','tendered'?,'reference'?]]]
     *
     * @throws OrderException
     */
    public function create(int $tenantId, int $userId, ?Shift $shift, array $data, float $discountLimitPercent = 100): Sale
    {
        $uuid = $data['client_uuid'] ?? null;
        if ($uuid && ($existing = Sale::forTenant($tenantId)->where('client_uuid', $uuid)->first())) {
            return $existing;
        }

        $settings = PosSettings::forTenant($tenantId)->first();
        if (($settings->require_shift ?? true) && (!$shift || !$shift->isOpen())) {
            throw new OrderException('Abre el turno de caja antes de vender.');
        }

        $items = collect($data['items'] ?? [])->map(fn ($i) => [
            'product_id' => (int) $i['product_id'], 'variant_id' => $i['variant_id'] ?? null, 'quantity' => max(1, (int) $i['quantity']),
        ])->all();
        if (!$items) {
            throw new OrderException('La venta no tiene productos.');
        }

        $nit = $this->clean($data['nit'] ?? null, 30);
        $shop = ShopSettings::where('tenant_id', $tenantId)->first();
        $options = [];
        if ($shop && $shop->isRestaurantStore()) {
            // En restaurante el pedido necesita un tipo: en mesa si hay mesa, si no para llevar. Nunca delivery.
            $types = array_keys($shop->enabledOrderTypes());
            $wanted = !empty($data['table_id']) ? ['dine_in', 'pickup'] : ['pickup', 'dine_in'];
            $options['order_type'] = collect($wanted)->first(fn ($t) => in_array($t, $types, true)) ?? array_diff($types, ['delivery'])[0] ?? null;
            if (!empty($data['table_id']) && ($table = PosTable::forTenant($tenantId)->find($data['table_id']))) {
                $options['table_label'] = $table->name;
            }
        }
        $order = app(OrderService::class)->create($tenantId, $items, [], null, $options + [
            'source' => 'pos', 'cashier_id' => $userId, 'walk_in' => true, 'counter' => true,
            'tax_id' => $nit, 'tax_name' => $this->clean($data['tax_name'] ?? null, 150),
        ]);

        $orders = app(OrderService::class);
        try {
            $percent = (float) ($data['discount_percent'] ?? 0);
            if ($percent > 0) {
                if ($percent > $discountLimitPercent) {
                    throw new OrderException('Tu límite de descuento es ' . $discountLimitPercent . '%.');
                }
                $order = $orders->applyDiscount($order, round((float) $order->subtotal * $percent / 100, 4), 'Descuento ' . $percent . '%', $userId);
            }

            return $this->settle($tenantId, $userId, $shift, $data, $order, $orders, $nit, $uuid);
        } catch (\Throwable $e) {
            // El pedido ya existe: si el cobro falla se anula para no dejar pedidos huérfanos ni stock reservado.
            try {
                $orders->cancel($order, 'Venta POS no cobrada');
            } catch (\Throwable $ignored) {
            }
            throw $e;
        }
    }

    protected function settle(int $tenantId, int $userId, ?Shift $shift, array $data, $order, OrderService $orders, ?string $nit, ?string $uuid): Sale
    {
        return Db::transaction(function () use ($tenantId, $userId, $shift, $data, $order, $orders, $nit, $uuid) {
            $sale = Sale::create([
                'tenant_id' => $tenantId, 'order_id' => $order->id, 'shift_id' => $shift?->id, 'terminal_id' => $shift?->terminal_id,
                'cashier_user_id' => $userId, 'table_id' => $data['table_id'] ?? null, 'tab_state' => 'closed',
                'nit' => $nit, 'tax_name' => $this->clean($data['tax_name'] ?? null, 150), 'client_uuid' => $uuid, 'closed_at' => now(),
            ]);

            $remaining = round((float) $order->grand_total, 4);
            $first = null;
            foreach ($data['payments'] ?? [] as $p) {
                $method = PaymentMethod::forTenant($tenantId)->where('is_active', true)->find($p['method_id'] ?? 0);
                if (!$method) {
                    throw new OrderException('Método de pago no válido.');
                }
                $given = round((float) ($p['tendered'] ?? $p['amount'] ?? 0), 4);
                $applied = min($remaining, round((float) ($p['amount'] ?? $given), 4));
                if ($applied <= 0) {
                    continue;
                }
                // Cobro con QR automático: solo vale si el banco ya lo confirmó y no se usó en otra venta.
                if (!empty($p['qr_reference'])) {
                    $this->assertQrPaid($tenantId, (string) $p['qr_reference'], $applied);
                    $p['reference'] = $p['qr_reference'];
                }
                // Solo el efectivo da cambio; otros métodos se cobran exacto.
                $change = $method->isCash() ? max(0, round($given - $applied, 4)) : 0;
                Payment::create([
                    'tenant_id' => $tenantId, 'sale_id' => $sale->id, 'shift_id' => $shift?->id, 'payment_method_id' => $method->id,
                    'amount' => $applied, 'tendered' => $method->isCash() ? $given : $applied, 'change_given' => $change,
                    'reference' => $this->clean($p['reference'] ?? null, 120), 'status' => 'completed', 'user_id' => $userId,
                ]);
                $remaining = round($remaining - $applied, 4);
                $first ??= $method;
            }

            if ($remaining > 0.009) {
                throw new OrderException('Falta cobrar ' . number_format($remaining, 2) . '.');
            }

            $orders->markPaid($order, $first?->gateway_id, null, $userId);

            return $sale;
        });
    }

    protected function assertQrPaid(int $tenantId, string $reference, float $amount): void
    {
        $qr = class_exists(\Aero\Pay\Models\QrCode::class)
            ? \Aero\Pay\Models\QrCode::where('tenant_id', $tenantId)->where('origin', 'pos')->where('internal_reference', $reference)->first()
            : null;
        if (!$qr || $qr->status !== 'paid') {
            throw new OrderException('El pago con QR todavía no está confirmado.');
        }
        if ((float) $qr->amount + 0.009 < $amount) {
            throw new OrderException('El QR pagado es por un monto menor al de la venta.');
        }
        if (Payment::where('tenant_id', $tenantId)->where('reference', $reference)->exists()) {
            throw new OrderException('Ese pago con QR ya se usó en otra venta.');
        }
    }

    /** Datos del recibo, listos para imprimir o enviar. */
    public function receipt(Sale $sale): array
    {
        $order = $sale->order()->with(['items', 'currency'])->first();
        $payments = $sale->payments()->with('method')->get();

        return [
            'id' => $sale->id, 'number' => $order->order_number, 'date' => $sale->created_at?->toIso8601String(),
            'currency' => $order->currency?->symbol ?: 'Bs',
            'items' => $order->items->map(fn ($i) => [
                'name' => $i->product_name_snapshot . ($i->variant_label_snapshot ? ' · ' . $i->variant_label_snapshot : ''), 'quantity' => (int) $i->quantity,
                'unit_price' => (float) $i->unit_price, 'total' => (float) $i->line_total,
            ])->values()->all(),
            'subtotal' => (float) $order->subtotal, 'discount' => (float) $order->discount_total, 'total' => (float) $order->grand_total,
            'payments' => $payments->map(fn ($p) => [
                'method' => $p->method?->label, 'kind' => $p->method?->kind, 'amount' => (float) $p->amount,
                'tendered' => (float) $p->tendered, 'change' => (float) $p->change_given,
            ])->all(),
            'change' => (float) $payments->sum('change_given'),
            'nit' => $sale->nit, 'tax_name' => $sale->tax_name,
            'invoice' => 'manual',
        ];
    }

    /** Texto del recibo para WhatsApp. */
    public function receiptText(array $r, string $businessName): string
    {
        $m = fn ($n) => $r['currency'] . ' ' . number_format((float) $n, 2, ',', '.');
        $lines = ['*' . $businessName . '*', 'Recibo ' . $r['number'], ''];
        foreach ($r['items'] as $i) {
            $lines[] = $i['quantity'] . ' x ' . $i['name'] . ' — ' . $m($i['total']);
        }
        $lines[] = '';
        if ($r['discount'] > 0) {
            $lines[] = 'Descuento: -' . $m($r['discount']);
        }
        $lines[] = '*Total: ' . $m($r['total']) . '*';
        foreach ($r['payments'] as $p) {
            $lines[] = $p['method'] . ': ' . $m($p['tendered'] ?: $p['amount']);
        }
        if ($r['change'] > 0) {
            $lines[] = 'Cambio: ' . $m($r['change']);
        }
        if (!empty($r['nit'])) {
            $lines[] = '';
            $lines[] = 'NIT/CI: ' . $r['nit'] . (!empty($r['tax_name']) ? ' · ' . $r['tax_name'] : '');
        }
        $lines[] = '';
        $lines[] = '¡Gracias por su compra!';

        return implode("\n", $lines);
    }

    protected function clean(?string $v, int $max): ?string
    {
        $v = trim((string) $v);

        return $v === '' ? null : mb_substr($v, 0, $max);
    }
}
