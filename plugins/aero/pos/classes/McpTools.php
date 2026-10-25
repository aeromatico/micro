<?php namespace Aero\Pos\Classes;

use Aero\Pos\Models\Payment;
use Aero\Pos\Models\PaymentMethod;
use Aero\Pos\Models\Sale;
use Aero\Pos\Models\Shift;
use Aero\Pos\Models\Terminal;
use Illuminate\Support\Carbon;

/**
 * Tools de MCP para leer la caja (POS): estado de turnos y efectivo, ventas del día por método de pago, ventas
 * recientes y pedidos en línea que esperan cobrarse en caja. Solo lectura. Se registran por `aero.mcp.registerTools`.
 */
class McpTools
{
    public static function definitions(): array
    {
        $base = ['module' => 'pos', 'plugin' => 'Aero.Pos', 'write' => false];

        return [
            'pos_overview' => $base + [
                'description' => 'Estado de la caja AHORA: cajas con turno abierto (quién, desde cuándo, efectivo esperado), ventas de hoy por método de pago, pedidos del local por cobrar y emergencias de mesa activas.',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass()],
                'handler' => [static::class, 'overview'],
            ],
            'pos_sales_recent' => $base + [
                'description' => 'Ventas recientes de la caja con su total, pagos, NIT, cajero y hora. Por defecto las de hoy, de la más nueva a la más antigua.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'date' => ['type' => 'string', 'description' => '«today» (por defecto), «yesterday» o AAAA-MM-DD.'],
                    'shift_id' => ['type' => 'integer', 'description' => 'Solo las de este turno.'],
                    'method' => ['type' => 'string', 'enum' => ['cash', 'qr', 'card', 'transfer', 'other'], 'description' => 'Solo ventas con algún pago de este tipo.'],
                    'limit' => ['type' => 'integer', 'description' => 'Máximo de ventas (por defecto 20).'],
                ]],
                'handler' => [static::class, 'salesRecent'],
            ],
            'pos_pending_orders' => $base + [
                'description' => 'Pedidos que los clientes hicieron desde su mesa o su celular y esperan cobrarse o verificarse en la caja (con su número del día, mesa, total y cómo pagarán).',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass()],
                'handler' => [static::class, 'pendingOrders'],
            ],
        ];
    }

    public static function overview(array $args, int $tenantId): array
    {
        $tz = static::tz($tenantId);
        [$from, $to] = static::dayRange($tz, 'today');

        $shifts = Shift::forTenant($tenantId)->where('status', 'open')->with('terminal')->get()->map(fn ($s) => [
            'caja' => $s->terminal?->name, 'turno' => $s->id, 'abierto_por' => static::userName($s->opened_by_user_id),
            'desde' => $s->opened_at?->copy()->setTimezone($tz)->format('H:i'), 'efectivo_inicial' => (float) $s->opening_cash,
            'efectivo_esperado' => app(ShiftService::class)->expectedCash($s),
        ])->all();

        $methods = PaymentMethod::forTenant($tenantId)->get()->keyBy('id');
        $pays = Payment::where('tenant_id', $tenantId)->where('status', 'completed')->whereBetween('created_at', [$from, $to])->get()->groupBy('payment_method_id');
        $byMethod = [];
        foreach ($pays as $mid => $rows) {
            $byMethod[$methods[$mid]->label ?? ('método ' . $mid)] = ['pagos' => $rows->count(), 'monto' => round((float) $rows->sum('amount'), 2)];
        }
        $sales = Sale::forTenant($tenantId)->whereBetween('created_at', [$from, $to])->with('order')->get();
        $pending = static::pendingQuery($tenantId)->get();
        $panics = \Aero\Shop\Classes\PanicService::active($tenantId);

        return [
            'ahora' => now($tz)->format('Y-m-d H:i'),
            'turnos_abiertos' => $shifts ?: 'ninguna caja tiene turno abierto',
            'ventas_hoy' => [
                'cantidad' => $sales->count(), 'total' => round((float) $sales->sum(fn ($s) => (float) $s->order?->grand_total), 2),
                'descuentos' => round((float) $sales->sum(fn ($s) => (float) $s->order?->discount_total), 2), 'por_metodo' => $byMethod,
            ],
            'pedidos_por_cobrar' => ['cantidad' => $pending->count(), 'total' => round((float) $pending->sum('grand_total'), 2)],
            'emergencias_activas' => $panics->map(fn ($a) => $a->label . ' (hace ' . max(0, (int) $a->created_at->diffInMinutes(now(), true)) . ' min)')->values()->all(),
        ];
    }

    public static function salesRecent(array $args, int $tenantId): array
    {
        $tz = static::tz($tenantId);
        [$from, $to] = static::dayRange($tz, (string) ($args['date'] ?? 'today'));
        $q = Sale::forTenant($tenantId)->whereBetween('created_at', [$from, $to])->with(['order', 'payments.method'])->orderByDesc('id');
        if (!empty($args['shift_id'])) {
            $q->where('shift_id', (int) $args['shift_id']);
        }
        if (!empty($args['method'])) {
            $q->whereHas('payments', fn ($p) => $p->whereHas('method', fn ($m) => $m->where('kind', $args['method'])));
        }
        $list = $q->limit(max(1, min(50, (int) ($args['limit'] ?? 20))))->get();

        return [
            'ventas' => $list->map(fn ($s) => array_filter([
                'id' => $s->id, 'pedido' => $s->order?->order_number, 'numero' => $s->order?->short_number, 'hora' => $s->created_at?->copy()->setTimezone($tz)->format('H:i'),
                'total' => round((float) $s->order?->grand_total, 2), 'descuento' => ((float) $s->order?->discount_total) ?: null,
                'pagos' => $s->payments->where('status', 'completed')->map(fn ($p) => ($p->method?->label ?? 'pago') . ' ' . number_format((float) $p->amount, 2))->values()->all(),
                'cambio' => ((float) $s->payments->sum('change_given')) ?: null, 'nit' => $s->nit, 'razon_social' => $s->tax_name,
                'cajero' => static::userName($s->cashier_user_id), 'turno' => $s->shift_id,
            ], fn ($v) => $v !== null && $v !== '' && $v !== []))->all(),
            'total' => $list->count(),
        ];
    }

    public static function pendingOrders(array $args, int $tenantId): array
    {
        $tz = static::tz($tenantId);
        $orders = static::pendingQuery($tenantId)->with(['customer', 'payment_gateway', 'items'])->orderBy('id')->limit(40)->get();

        return [
            'pedidos' => $orders->map(fn ($o) => array_filter([
                'numero' => $o->short_number, 'numero_pedido' => $o->order_number, 'tipo' => $o->order_type, 'mesa' => $o->table_label, 'cliente' => $o->customer?->first_name,
                'total' => round((float) $o->grand_total, 2), 'hora' => $o->created_at?->copy()->setTimezone($tz)->format('H:i'), 'cocina' => $o->kitchen_status,
                'pago' => !$o->payment_gateway ? 'efectivo en caja' : ($o->payment_gateway->driver === 'pagos_qr' ? 'pago QR (se verifica solo)' : $o->payment_gateway->name),
                'platos' => $o->items->map(fn ($i) => $i->quantity . '× ' . $i->product_name_snapshot)->values()->all(),
            ], fn ($v) => $v !== null && $v !== ''))->all(),
            'total' => $orders->count(),
        ];
    }

    protected static function pendingQuery(int $tenantId)
    {
        return \Aero\Shop\Models\Order::forTenant($tenantId)->whereNotNull('order_type')->where('source', '!=', 'pos')->whereNull('paid_at')
            ->whereIn('status', ['pending', 'awaiting_payment'])->where('created_at', '>=', now()->subHours(24));
    }

    protected static function tz(int $tenantId): string
    {
        return \Aero\Shop\Models\ShopSettings::where('tenant_id', $tenantId)->value('timezone') ?: 'America/La_Paz';
    }

    protected static function dayRange(string $tz, string $date): array
    {
        $day = match (strtolower($date)) {
            'today', 'hoy', '' => now($tz),
            'yesterday', 'ayer' => now($tz)->subDay(),
            default => (function () use ($date, $tz) { try { return Carbon::parse($date, $tz); } catch (\Throwable $e) { return now($tz); } })(),
        };

        return [$day->copy()->startOfDay()->utc(), $day->copy()->endOfDay()->utc()];
    }

    protected static function userName(?int $id): ?string
    {
        if (!$id) {
            return null;
        }
        $u = \Backend\Models\User::find($id);

        return $u ? (trim($u->first_name . ' ' . $u->last_name) ?: $u->login) : null;
    }
}
