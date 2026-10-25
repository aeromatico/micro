<?php namespace Aero\Shop\Classes\Mcp;

use Aero\Shop\Classes\PanicService;
use Aero\Shop\Models\Order;
use Aero\Shop\Models\ShopSettings;
use Illuminate\Support\Carbon;

/**
 * Tools de MCP para dar información rápida del restaurante y estar atento a lo que pasa: resumen, estado de la
 * cocina, pedidos y alertas (emergencias de mesa, pedidos sin aceptar, retrasos, listos sin entregar, por cobrar).
 * Todas son de solo lectura. Se registran por el evento `aero.mcp.registerTools` (ver Plugin::bootMcpTools).
 */
class RestaurantTools
{
    public static function definitions(): array
    {
        $base = ['module' => 'shop', 'plugin' => 'Aero.Shop', 'write' => false];
        $status = ['type' => 'string', 'enum' => ['new', 'preparing', 'ready', 'delivered', 'active', 'all'], 'description' => 'Estado en cocina: new=nuevos, preparing=preparando, ready=listos, delivered=entregados, active=nuevos+preparando+listos (por defecto), all=todos.'];

        return [
            'restaurant_overview' => $base + [
                'description' => 'Resumen rápido del restaurante AHORA: si está abierto, la cocina (nuevos, preparando, listos, retrasados), las ventas y pedidos de hoy, las emergencias activas y una lista corta de lo que requiere atención. Ideal como primera consulta.',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass()],
                'handler' => [static::class, 'overview'],
            ],
            'restaurant_alerts' => $base + [
                'description' => 'Lo que requiere atención ahora, ordenado por prioridad: emergencias de mesa (críticas), pedidos nuevos sin aceptar, pedidos retrasados, pedidos listos sin entregar y cuentas por cobrar. Úsala para estar atento: consúltala al empezar y cada vez que pregunten «¿qué pasa?». Con `since` devuelve además lo que llegó desde esa hora.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'since' => ['type' => 'string', 'description' => 'Fecha y hora ISO 8601 de tu última consulta; se agrega «nuevo desde entonces».'],
                    'new_wait_minutes' => ['type' => 'integer', 'description' => 'Minutos sin aceptar para avisar de un pedido nuevo (por defecto 5).'],
                    'ready_wait_minutes' => ['type' => 'integer', 'description' => 'Minutos de un pedido listo sin entregar para avisar (por defecto 10).'],
                    'unpaid_wait_minutes' => ['type' => 'integer', 'description' => 'Minutos de una cuenta sin cobrar para avisar (por defecto 30).'],
                ]],
                'handler' => [static::class, 'alerts'],
            ],
            'kitchen_board' => $base + [
                'description' => 'Estado de la cocina como el tablero: pedidos por columna con mesa, tipo, platos con extras y notas, minutos de espera, hora prometida y si van retrasados.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'status' => $status,
                    'order_type' => ['type' => 'string', 'enum' => ['dine_in', 'pickup', 'delivery'], 'description' => 'Filtra por tipo de pedido.'],
                    'limit' => ['type' => 'integer', 'description' => 'Máximo de pedidos (por defecto 30).'],
                ]],
                'handler' => [static::class, 'kitchenBoard'],
            ],
            'restaurant_orders' => $base + [
                'description' => 'Busca pedidos del restaurante. Por número corto o del día («18»), número de pedido completo (PED-000018), mesa, cliente, estado, tipo, cobrado o no, y fecha (hoy por defecto). Devuelve platos, total y estado de cocina.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'number' => ['type' => 'string', 'description' => 'Número del día («18») o número de pedido completo («PED-000018»).'],
                    'table' => ['type' => 'string', 'description' => 'Número de mesa.'],
                    'customer' => ['type' => 'string', 'description' => 'Parte del nombre del cliente.'],
                    'status' => $status,
                    'order_type' => ['type' => 'string', 'enum' => ['dine_in', 'pickup', 'delivery']],
                    'paid' => ['type' => 'boolean', 'description' => 'true = ya cobrados; false = por cobrar.'],
                    'date' => ['type' => 'string', 'description' => '«today» (por defecto), «yesterday» o una fecha AAAA-MM-DD en la zona horaria de la tienda.'],
                    'limit' => ['type' => 'integer', 'description' => 'Máximo de pedidos (por defecto 20).'],
                ]],
                'handler' => [static::class, 'orders'],
            ],
        ];
    }

    // ------------------------------------------------------------------ tools

    public static function overview(array $args, int $tenantId): array
    {
        $ctx = static::context($tenantId);
        if (isset($ctx['error'])) {
            return $ctx;
        }
        ['tz' => $tz, 'settings' => $s] = $ctx;
        [$from, $to] = static::dayRange($tz, 'today');

        $active = Order::forTenant($tenantId)->whereIn('kitchen_status', ['new', 'preparing', 'ready'])->whereNotIn('status', ['cancelled', 'refunded'])->with('items')->get();
        $late = $active->filter(fn ($o) => static::isLate($o))->count();
        $today = Order::forTenant($tenantId)->whereNotIn('status', ['cancelled', 'refunded'])->whereBetween('created_at', [$from, $to])->get();
        $panics = PanicService::active($tenantId);
        $alerts = static::buildAlerts($tenantId, $tz, 5, 10, 30)['alerts'];

        return [
            'ahora' => now($tz)->format('Y-m-d H:i'),
            'abierto' => (bool) $s->isOpenNow(),
            'recibiendo_pedidos' => (bool) $s->accepting_orders,
            'modo_ocupado' => !empty($s->restaurant()['busy']),
            'cocina' => [
                'nuevos' => $active->where('kitchen_status', 'new')->count(),
                'preparando' => $active->where('kitchen_status', 'preparing')->count(),
                'listos_sin_entregar' => $active->where('kitchen_status', 'ready')->count(),
                'retrasados' => $late,
            ],
            'hoy' => [
                'pedidos' => $today->count(),
                'vendido' => round((float) $today->sum('grand_total'), 2),
                'cobrado' => round((float) $today->whereNotNull('paid_at')->sum('grand_total'), 2),
                'por_cobrar' => $today->whereNull('paid_at')->count(),
                'por_tipo' => $today->groupBy('order_type')->map->count()->all(),
                'por_origen' => $today->groupBy('source')->map->count()->all(),
            ],
            'emergencias_activas' => $panics->map(fn ($a) => ['mesa' => $a->label, 'hace_min' => static::minutes($a->created_at), 'pedido' => $a->order?->short_number])->values()->all(),
            'requiere_atencion' => array_map(fn ($a) => $a['mensaje'], array_slice($alerts, 0, 8)),
        ];
    }

    public static function alerts(array $args, int $tenantId): array
    {
        $ctx = static::context($tenantId);
        if (isset($ctx['error'])) {
            return $ctx;
        }
        $res = static::buildAlerts($tenantId, $ctx['tz'], (int) ($args['new_wait_minutes'] ?? 5), (int) ($args['ready_wait_minutes'] ?? 10), (int) ($args['unpaid_wait_minutes'] ?? 30));

        if (!empty($args['since'])) {
            try {
                $since = Carbon::parse($args['since']);
                $res['desde'] = $since->setTimezone($ctx['tz'])->format('Y-m-d H:i');
                $res['pedidos_nuevos_desde'] = Order::forTenant($tenantId)->whereNotNull('order_type')->where('created_at', '>', $since)->with(['items', 'customer'])->orderBy('id')->limit(30)->get()
                    ->map(fn ($o) => static::brief($o))->all();
                $res['emergencias_nuevas_desde'] = PanicService::active($tenantId)->filter(fn ($a) => $a->created_at->gt($since))->map(fn ($a) => $a->label)->values()->all();
            } catch (\Throwable $e) {
                $res['aviso'] = 'No pude leer `since`; usa fecha y hora ISO 8601.';
            }
        }

        return $res;
    }

    public static function kitchenBoard(array $args, int $tenantId): array
    {
        $ctx = static::context($tenantId);
        if (isset($ctx['error'])) {
            return $ctx;
        }
        $q = Order::forTenant($tenantId)->whereNotIn('status', ['cancelled', 'refunded'])->whereNotNull('kitchen_status')->with(['items', 'customer'])->orderBy('created_at');
        static::applyStatus($q, $args['status'] ?? 'active');
        if (!empty($args['order_type'])) {
            $q->where('order_type', $args['order_type']);
        }
        if (($args['status'] ?? 'active') === 'delivered') {
            $q->where('kitchen_updated_at', '>=', now()->subHours(3));
        }
        $orders = $q->limit(max(1, min(60, (int) ($args['limit'] ?? 30))))->get();

        return [
            'ahora' => now($ctx['tz'])->format('H:i'),
            'columnas' => $orders->groupBy('kitchen_status')->map(fn ($g) => $g->map(fn ($o) => static::brief($o, $ctx['tz']))->values()->all())->all(),
            'total' => $orders->count(),
        ];
    }

    public static function orders(array $args, int $tenantId): array
    {
        $ctx = static::context($tenantId);
        if (isset($ctx['error'])) {
            return $ctx;
        }
        [$from, $to] = static::dayRange($ctx['tz'], (string) ($args['date'] ?? 'today'));
        $q = Order::forTenant($tenantId)->whereNotNull('order_type')->with(['items', 'customer'])->orderByDesc('id');

        if (!empty($args['number'])) {
            $n = ltrim(strtoupper(trim((string) $args['number'])), '#');
            if (ctype_digit($n)) {
                // El número del día se repite cada día: se limita a la fecha pedida (hoy por defecto).
                $q->where(fn ($w) => $w->where(fn ($d) => $d->where('daily_number', (int) $n)->whereBetween('created_at', [$from, $to]))
                    ->orWhere(fn ($d) => $d->whereNull('daily_number')->where('order_number', 'like', '%' . str_pad($n, 6, '0', STR_PAD_LEFT))));
            } else {
                $q->where('order_number', $n);
            }
        } else {
            $q->whereBetween('created_at', [$from, $to]);
        }
        if (!empty($args['table'])) {
            $q->where('table_label', (string) $args['table']);
        }
        if (!empty($args['customer'])) {
            $name = '%' . str_replace(['%', '_'], '', (string) $args['customer']) . '%';
            $q->whereHas('customer', fn ($c) => $c->where('first_name', 'like', $name)->orWhere('last_name', 'like', $name));
        }
        if (!empty($args['order_type'])) {
            $q->where('order_type', $args['order_type']);
        }
        if (isset($args['paid'])) {
            $args['paid'] ? $q->whereNotNull('paid_at') : $q->whereNull('paid_at')->whereNotIn('status', ['cancelled', 'refunded']);
        }
        static::applyStatus($q, $args['status'] ?? 'all');

        $list = $q->limit(max(1, min(50, (int) ($args['limit'] ?? 20))))->get();

        return ['pedidos' => $list->map(fn ($o) => static::brief($o, $ctx['tz']))->all(), 'total' => $list->count()];
    }

    // ------------------------------------------------------------------ alertas

    /** @return array{alerts: array, resumen: string, consultado: string} */
    public static function buildAlerts(int $tenantId, string $tz, int $newWait, int $readyWait, int $unpaidWait): array
    {
        $alerts = [];
        $add = function (string $sev, string $type, string $msg, ?string $table = null, ?string $short = null, ?int $min = null) use (&$alerts) {
            $alerts[] = ['prioridad' => $sev, 'tipo' => $type, 'mensaje' => $msg, 'mesa' => $table, 'pedido' => $short, 'minutos' => $min];
        };

        foreach (PanicService::active($tenantId) as $a) {
            $m = static::minutes($a->created_at);
            $add('critica', 'emergencia', "EMERGENCIA en {$a->label} hace {$m} min (sin atender)", $a->table_label, $a->order?->short_number, $m);
        }

        $open = Order::forTenant($tenantId)->whereNotIn('status', ['cancelled', 'refunded'])->whereNotNull('kitchen_status')->where('created_at', '>=', now()->subHours(24))->with(['items', 'customer'])->orderBy('created_at')->get();
        foreach ($open as $o) {
            $w = static::where($o);
            $age = static::minutes($o->created_at);
            if ($o->kitchen_status === 'new' && $age >= $newWait) {
                $add('alta', 'pedido_nuevo_sin_aceptar', "Pedido {$o->short_number} ({$w}) sin aceptar hace {$age} min", $o->table_label, $o->short_number, $age);
            } elseif ($o->kitchen_status === 'preparing' && static::isLate($o)) {
                $late = $o->promised_at ? static::minutes($o->promised_at) : $age;
                $add('alta', 'retrasado', "Pedido {$o->short_number} ({$w}) retrasado {$late} min", $o->table_label, $o->short_number, $late);
            } elseif ($o->kitchen_status === 'ready' && ($o->ready_at ? static::minutes($o->ready_at) : $age) >= $readyWait) {
                $m = $o->ready_at ? static::minutes($o->ready_at) : $age;
                $add('media', 'listo_sin_entregar', "Pedido {$o->short_number} ({$w}) listo hace {$m} min y sin entregar", $o->table_label, $o->short_number, $m);
            }
            if (!$o->paid_at && in_array($o->kitchen_status, ['ready', 'delivered'], true) && $age >= $unpaidWait) {
                $add('media', 'por_cobrar', "Pedido {$o->short_number} ({$w}) sigue sin cobrar hace {$age} min (Bs " . number_format((float) $o->grand_total, 2) . ')', $o->table_label, $o->short_number, $age);
            }
        }

        $rank = ['critica' => 0, 'alta' => 1, 'media' => 2];
        usort($alerts, fn ($a, $b) => [$rank[$a['prioridad']], -($a['minutos'] ?? 0)] <=> [$rank[$b['prioridad']], -($b['minutos'] ?? 0)]);

        return [
            'alerts' => $alerts,
            'resumen' => $alerts ? count($alerts) . ' cosa(s) requieren atención; la más urgente: ' . $alerts[0]['mensaje'] : 'Todo en orden: sin emergencias, retrasos ni pedidos esperando.',
            'consultado' => now($tz)->format('Y-m-d H:i'),
        ];
    }

    // ------------------------------------------------------------------ apoyo

    protected static function context(int $tenantId): array
    {
        $s = ShopSettings::where('tenant_id', $tenantId)->first();
        if (!$s || !$s->is_enabled) {
            return ['error' => 'La tienda de este negocio no está activada.'];
        }
        if (!$s->isRestaurantStore()) {
            return ['error' => 'Este negocio no está en modo Restaurante (Tienda → Configuración).'];
        }

        return ['settings' => $s, 'tz' => $s->timezone ?: 'America/La_Paz'];
    }

    /** @return array{0: Carbon, 1: Carbon} inicio y fin del día (UTC) de «today», «yesterday» o AAAA-MM-DD en la zona de la tienda. */
    protected static function dayRange(string $tz, string $date): array
    {
        $day = match (strtolower($date)) {
            'today', 'hoy', '' => now($tz),
            'yesterday', 'ayer' => now($tz)->subDay(),
            default => (function () use ($date, $tz) { try { return Carbon::parse($date, $tz); } catch (\Throwable $e) { return now($tz); } })(),
        };

        return [$day->copy()->startOfDay()->utc(), $day->copy()->endOfDay()->utc()];
    }

    protected static function applyStatus($q, string $status): void
    {
        match ($status) {
            'new', 'preparing', 'ready', 'delivered' => $q->where('kitchen_status', $status),
            'active' => $q->whereIn('kitchen_status', ['new', 'preparing', 'ready']),
            default => null,
        };
    }

    protected static function minutes($at): int
    {
        return $at ? max(0, (int) round(abs(Carbon::parse($at)->diffInSeconds(now(), true)) / 60)) : 0;
    }

    protected static function isLate(Order $o): bool
    {
        if ($o->kitchen_status !== 'preparing') {
            return false;
        }

        return $o->promised_at ? $o->promised_at->isPast() : static::minutes($o->created_at) > 30;
    }

    protected static function where(Order $o): string
    {
        return match ($o->order_type) {
            'dine_in' => 'Mesa ' . ($o->table_label ?: 's/n'),
            'pickup' => 'recoger',
            'delivery' => 'delivery',
            default => (string) $o->order_type,
        };
    }

    /** Forma compacta de un pedido para la IA. */
    public static function brief(Order $o, ?string $tz = null): array
    {
        $tz ??= 'America/La_Paz';

        return array_filter([
            'numero' => $o->short_number, 'numero_pedido' => $o->order_number, 'tipo' => $o->order_type, 'mesa' => $o->table_label,
            'cliente' => $o->customer?->first_name, 'origen' => $o->source, 'estado' => $o->status, 'cocina' => $o->kitchen_status,
            'cobrado' => (bool) $o->paid_at, 'total' => round((float) $o->grand_total, 2),
            'hace_min' => static::minutes($o->created_at), 'hora' => $o->created_at?->copy()->setTimezone($tz)->format('H:i'),
            'prometido' => $o->promised_at?->copy()->setTimezone($tz)->format('H:i'), 'retrasado' => static::isLate($o) ?: null,
            'programado_para' => $o->scheduled_for?->copy()->setTimezone($tz)->format('Y-m-d H:i'),
            'platos' => $o->items->map(function ($i) {
                $extras = collect((array) $i->modifiers)->pluck('name')->filter()->implode(', ');

                return $i->quantity . '× ' . $i->product_name_snapshot . ($i->variant_label_snapshot ? ' · ' . $i->variant_label_snapshot : '')
                    . ($extras ? ' (' . $extras . ')' : '') . ($i->note ? ' «' . $i->note . '»' : '');
            })->values()->all(),
            'nota_cliente' => $o->customer_notes,
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);
    }
}
