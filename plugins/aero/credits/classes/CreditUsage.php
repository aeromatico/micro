<?php namespace Aero\Credits\Classes;

use Aero\Credits\Models\CreditAction;
use Aero\Credits\Models\CreditTransaction;
use Aero\Credits\Models\CreditType;
use Illuminate\Support\Facades\DB;

/**
 * Lectura transparente del consumo: traduce los `action_code` del ledger a
 * función + categoría + unidad que entiende el tenant, y arma el resumen de
 * "¿a dónde van mis créditos?" y el tarifario público. Solo lee; nunca cobra.
 */
class CreditUsage
{
    /** Prefijo del código → categoría. El primer prefijo que coincide gana. */
    public const CATEGORIES = [
        'hello'      => 'Mensajería',
        'chat'       => 'Mensajería',
        'sms'        => 'Mensajería',
        'aifields'   => 'Inteligencia artificial',
        'chatbots'   => 'Inteligencia artificial',
        'hub'        => 'Datos externos',
        'api'        => 'API',
        'workflows'  => 'Automatización',
        'connector'  => 'Automatización',
        'pay'        => 'Pagos',
        'services'   => 'Servicios',
        'workspaces' => 'Servicios',
        'producer'   => 'Video',
    ];

    /** Prefijo del código → plugin de origen cuando el cobro no lo declara. */
    public const PLUGINS = [
        'hello' => 'Aero.Hello', 'chat' => 'Aero.Chat', 'sms' => 'Aero.Sms', 'aifields' => 'Aero.AiFields',
        'chatbots' => 'Aero.Chatbots', 'hub' => 'Aero.Hub', 'api' => 'Aero.Api', 'workflows' => 'Aero.Workflows',
        'connector' => 'Aero.Connector', 'pay' => 'Aero.Pay', 'services' => 'Aero.Services',
        'workspaces' => 'Aero.Workspaces', 'producer' => 'Aero.Producer',
    ];

    /** Código exacto o prefijo → unidad de cobro. */
    public const UNITS = [
        'hello.message_send' => 'por mensaje',
        'hello.voice_call'   => 'por llamada',
        'sms.segment'        => 'por segmento',
        'workflows.run'      => 'por ejecución',
        'services.purchase'  => 'por compra',
        'pay.qr_completed'   => 'por QR cobrado',
        'chatbots.ai_reply'  => 'por respuesta',
        'connector.'         => 'por llamada',
        'api.request'        => 'por petición',
        'hub.mediaQueue'     => 'por trabajo',
        'hub.'               => 'por consulta',
        'aifields.'          => 'por uso',
        'producer.'          => 'por video',
    ];

    /** Funciones sin fila en el catálogo (código dinámico o cobro directo). */
    public const FALLBACK_LABELS = [
        'chatbots.ai_reply'  => 'Respuesta de IA en chatbots',
        'connector.'         => 'Llamadas a conectores',
        'services.purchase'  => 'Compra de servicios',
        'workspaces.'        => 'Workspaces',
        'producer.'          => 'Pedido de video',
        'hub.'               => 'Datos externos',
    ];

    /** Catálogo cargado una vez por consulta (summary/tariffs lo refrescan). */
    protected static ?array $actions = null;

    public static function flush(): void
    {
        static::$actions = null;
    }

    protected static function action(string $code): ?CreditAction
    {
        static::$actions ??= CreditAction::get()->keyBy('code')->all();

        return static::$actions[$code] ?? null;
    }

    public static function categoryFor(string $code): string
    {
        return self::CATEGORIES[strstr($code, '.', true) ?: $code] ?? 'Otros';
    }

    public static function pluginFor(string $code): ?string
    {
        return self::PLUGINS[strstr($code, '.', true) ?: $code] ?? null;
    }

    public static function unitFor(string $code): string
    {
        foreach (self::UNITS as $key => $unit) {
            if ($code === $key || (str_ends_with($key, '.') && str_starts_with($code, $key))) {
                return $unit;
            }
        }

        return 'por uso';
    }

    /**
     * Función tal como la ve el tenant. Normaliza códigos dinámicos
     * (`connector.12`, `services.purchase.gimnasio`) a una sola función.
     *
     * @return array{key:string,label:string,category:string,unit:string}
     */
    public static function describe(?string $code): array
    {
        $code = (string) $code;

        if ($code === '') {
            return ['key' => '', 'label' => 'Movimiento de saldo', 'category' => 'Otros', 'unit' => ''];
        }

        if ($action = static::action($code)) {
            return [
                'key'      => $code,
                'label'    => $action->public_label ?: $action->label,
                'category' => $action->category ?: self::categoryFor($code),
                'unit'     => $action->unit ?: self::unitFor($code),
            ];
        }

        foreach (self::FALLBACK_LABELS as $key => $label) {
            if ($code === $key || str_starts_with($code, rtrim($key, '.') . '.')) {
                return [
                    'key'      => rtrim($key, '.'),
                    'label'    => $label,
                    'category' => self::categoryFor($code),
                    'unit'     => self::unitFor($code),
                ];
            }
        }

        return ['key' => $code, 'label' => $code, 'category' => 'Otros', 'unit' => 'por uso'];
    }

    /**
     * Consumo neto (cobros − reembolsos) del tenant en los últimos $days días,
     * agrupado por categoría → función → moneda.
     *
     * @return array{categories:array,totals:array,days:int}
     */
    public static function summary(int $tenantId, int $days = 30): array
    {
        static::flush();

        $rows = CreditTransaction::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('kind', ['charge', 'refund'])
            ->whereNotNull('action_code')
            ->where('created_at', '>=', now()->subDays($days))
            ->select('action_code', 'credit_type_id', 'kind', DB::raw('COUNT(*) n'), DB::raw('SUM(delta) s'))
            ->groupBy('action_code', 'credit_type_id', 'kind')
            ->get();

        $types = CreditType::get()->keyBy('id');
        $funcs = [];

        foreach ($rows as $r) {
            $d = self::describe($r->action_code);
            $type = $types[$r->credit_type_id] ?? null;
            $k = $d['key'] . '|' . $r->credit_type_id;
            $sign = $r->kind === 'charge' ? 1 : -1;

            $funcs[$k] ??= $d + [
                'type_code' => $type->code ?? '', 'type_label' => $type->label ?? '', 'color' => $type->color ?? '#999',
                'is_money' => (bool) ($type->is_money ?? false), 'uses' => 0, 'amount' => 0,
            ];
            $funcs[$k]['uses'] += $sign * (int) $r->n;
            $funcs[$k]['amount'] += -(int) $r->s; // charge: delta<0 suma; refund: delta>0 resta
        }

        $categories = $totals = [];

        foreach ($funcs as $f) {
            if ($f['amount'] <= 0) {
                continue;
            }

            $categories[$f['category']]['rows'][] = $f;
            $categories[$f['category']]['totals'][$f['type_code']] = [
                'label' => $f['type_label'], 'color' => $f['color'], 'is_money' => $f['is_money'],
                'amount' => ($categories[$f['category']]['totals'][$f['type_code']]['amount'] ?? 0) + $f['amount'],
            ];
            $totals[$f['type_code']] = [
                'label' => $f['type_label'], 'color' => $f['color'], 'is_money' => $f['is_money'],
                'amount' => ($totals[$f['type_code']]['amount'] ?? 0) + $f['amount'],
            ];
        }

        foreach ($categories as &$c) {
            usort($c['rows'], fn ($a, $b) => $b['uses'] <=> $a['uses']);
        }
        unset($c);

        // Monedas distintas no se suman entre sí: se ordena por cantidad de usos.
        uasort($categories, fn ($a, $b) => array_sum(array_column($b['rows'], 'uses')) <=> array_sum(array_column($a['rows'], 'uses')));

        return ['categories' => $categories, 'totals' => $totals, 'days' => $days];
    }

    /** Tarifario público: acciones activas, públicas y con costo, por categoría. */
    public static function tariffs(): array
    {
        static::flush();
        $out = [];

        $actions = CreditAction::with('creditType')->where('is_active', true)->where('is_public', true)
            ->where('default_cost', '>', 0)->get();

        foreach ($actions as $a) {
            $out[$a->category ?: self::categoryFor($a->code)][] = [
                'label'       => $a->public_label ?: $a->label,
                'description' => $a->public_description,
                'unit'        => $a->unit ?: self::unitFor($a->code),
                'cost'        => (int) $a->default_cost,
                'type_label'  => $a->creditType->label ?? '',
                'color'       => $a->creditType->color ?? '#999',
                'is_money'    => (bool) ($a->creditType->is_money ?? false),
            ];
        }

        ksort($out);

        foreach ($out as &$rows) {
            usort($rows, fn ($x, $y) => strcmp($x['label'], $y['label']));
        }

        return $out;
    }
}
