<?php namespace Aero\Pos\Http\Controllers;

use Aero\Pos\Classes\SaleService;
use Aero\Pos\Models\Sale;
use Aero\Pos\Models\Shift;
use Aero\Shop\Classes\Exceptions\OrderException;
use Aero\Shop\Models\Order;
use Aero\Shop\Models\ShopSettings;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Pedidos que los clientes hacen en línea desde el local (checkout del restaurante): la caja los consulta por
 * su número corto («18»), los verifica (QR) y los cobra (efectivo en caja) sobre el mismo pedido.
 */
class OrdersController extends Controller
{
    protected const TYPES = ['dine_in' => 'En el local', 'pickup' => 'Recoger', 'delivery' => 'Delivery'];

    /** GET orders?q= — pedidos de las últimas 24 h: primero los por cobrar. */
    public function index(Request $request)
    {
        $tid = $request->attributes->get('tenant_id');
        $q = trim((string) $request->query('q'));

        $today = \Aero\Shop\Classes\DailyNumber::today($tid);
        // Los números del día se repiten cada día: se muestran los de hoy y, solo si siguen sin pagar, los de ayer.
        $orders = Order::forTenant($tid)->whereNotNull('order_type')->where('source', '!=', 'pos')
            ->whereNotIn('status', ['cancelled', 'refunded'])->where('created_at', '>=', now()->subHours(24))
            ->where(fn ($w) => $w->whereNull('paid_at')->orWhereNull('kitchen_status')->orWhere('kitchen_status', '!=', 'delivered'))
            ->where(fn ($w) => $w->whereNull('daily_date')->orWhere('daily_date', $today)->orWhereNull('paid_at'))
            ->with(['customer', 'payment_gateway'])->orderByRaw('paid_at is null desc')->orderByRaw('daily_date = ? desc', [$today])->orderByDesc('id')->limit(80)->get();

        if ($q !== '') {
            $needle = mb_strtolower(ltrim($q, '#'));
            $orders = $orders->filter(fn ($o) => $o->short_number === ltrim($needle, '0') || str_starts_with($o->short_number, $needle)
                || str_contains(mb_strtolower((string) $o->customer?->full_name), $needle) || str_contains(mb_strtolower((string) $o->table_label), $needle))->values();
        }

        return response()->json(['data' => [
            'orders' => $orders->map(fn ($o) => $this->present($o))->all(),
            'pending' => $orders->whereNull('paid_at')->count(),
        ]]);
    }

    /** GET orders/{id} */
    public function show(Request $request, int $id)
    {
        $order = $this->find($request, $id);

        return $order ? response()->json(['data' => ['order' => $this->present($order, true)]]) : $this->missing();
    }

    /** POST orders/{id}/verify — consulta al banco si el QR del pedido ya se pagó (si sí, queda pagado). */
    public function verify(Request $request, int $id)
    {
        $order = $this->find($request, $id);
        if (!$order) {
            return $this->missing();
        }
        if (!$order->paid_at && $order->payment_gateway?->driver === 'pagos_qr' && class_exists(\Aero\Pay\Models\QrCode::class)) {
            $qr = \Aero\Pay\Models\QrCode::where('tenant_id', $order->tenant_id)->where('internal_reference', $order->payment_reference)->first();
            if ($qr && $qr->status === 'pending' && $qr->flow === 'qr_dynamic') {
                try {
                    app(\Aero\Pay\Classes\QrStatusReconciler::class)->reconcile($qr);
                } catch (\Throwable $e) {
                    // el banco no respondió: se informa como sigue pendiente
                }
            }
            $order = $this->find($request, $id);
        }

        return response()->json(['data' => ['order' => $this->present($order, true)]]);
    }

    /** POST orders/{id}/collect — cobra el pedido en caja (misma forma de pagos que una venta). */
    public function collect(Request $request, int $id)
    {
        $data = $request->validate([
            'client_uuid' => 'required|uuid', 'shift_id' => 'nullable|integer', 'nit' => 'nullable|string|max:30', 'tax_name' => 'nullable|string|max:150',
            'payments' => 'required|array|min:1|max:6', 'payments.*.method_id' => 'required|integer', 'payments.*.amount' => 'nullable|numeric|min:0',
            'payments.*.tendered' => 'nullable|numeric|min:0', 'payments.*.reference' => 'nullable|string|max:120', 'payments.*.qr_reference' => 'nullable|string|max:64',
        ]);
        $order = $this->find($request, $id);
        if (!$order) {
            return $this->missing();
        }
        $tid = $request->attributes->get('tenant_id');
        $shift = !empty($data['shift_id']) ? Shift::forTenant($tid)->find($data['shift_id']) : null;

        try {
            $svc = app(SaleService::class);
            $sale = $svc->collectOrder($tid, $request->attributes->get('pos_user')->id, $shift, $order, $data);

            return response()->json(['data' => ['receipt' => $svc->receipt($sale), 'order' => $this->present($this->find($request, $id), true)]], 201);
        } catch (OrderException $e) {
            return response()->json(['error' => 'rejected', 'message' => $e->getMessage()], 422);
        }
    }

    protected function find(Request $request, int $id): ?Order
    {
        return Order::forTenant($request->attributes->get('tenant_id'))->whereNotNull('order_type')->with(['customer', 'payment_gateway', 'items'])->find($id);
    }

    protected function missing()
    {
        return response()->json(['error' => 'not_found', 'message' => 'Pedido no encontrado.'], 404);
    }

    protected function present(Order $o, bool $detail = false): array
    {
        $kind = !$o->payment_gateway ? 'cash' : ($o->payment_gateway->driver === 'pagos_qr' ? 'qr' : 'other');
        $qrStatus = null;
        if ($kind === 'qr' && !$o->paid_at && class_exists(\Aero\Pay\Models\QrCode::class)) {
            $qrStatus = \Aero\Pay\Models\QrCode::where('tenant_id', $o->tenant_id)->where('internal_reference', $o->payment_reference)->value('status');
        }
        $out = [
            'id' => $o->id, 'short' => $o->short_number, 'number' => $o->order_number, 'type' => $o->order_type, 'type_label' => self::TYPES[$o->order_type] ?? $o->order_type,
            'table' => $o->table_label, 'customer' => $o->customer?->full_name, 'phone' => $o->customer?->phone,
            'total' => (float) $o->grand_total, 'paid' => (bool) $o->paid_at, 'status' => $o->status, 'kitchen' => $o->kitchen_status,
            'payment' => ['kind' => $kind, 'label' => $kind === 'cash' ? 'Efectivo en caja' : ($kind === 'qr' ? 'Pago QR' : $o->payment_gateway->name), 'qr_status' => $qrStatus],
            'by_pos' => Sale::where('order_id', $o->id)->exists(), 'older' => $o->daily_date && (string) $o->daily_date !== \Aero\Shop\Classes\DailyNumber::today($o->tenant_id), 'created_at' => $o->created_at?->toIso8601String(),
            // Se puede cobrar en caja si no está pagado y no espera un QR vigente.
            'can_collect' => !$o->paid_at && in_array($o->status, ['pending', 'awaiting_payment'], true) && $qrStatus !== 'pending',
            'items_count' => (int) $o->items->sum('quantity'),
        ];
        if ($detail) {
            $out['items'] = $o->items->map(fn ($i) => [
                'name' => $i->product_name_snapshot . ($i->variant_label_snapshot ? ' · ' . $i->variant_label_snapshot : ''), 'quantity' => (int) $i->quantity,
                'unit_price' => (float) $i->unit_price, 'total' => (float) $i->line_total,
                'extras' => collect((array) $i->modifiers)->pluck('name')->filter()->values()->all(), 'note' => $i->note,
            ])->values()->all();
            $out['notes'] = $o->customer_notes;
            $out['scheduled_for'] = $o->scheduled_for?->toIso8601String();
            $out['subtotal'] = (float) $o->subtotal; $out['shipping'] = (float) $o->shipping_total;
        }

        return $out;
    }
}
