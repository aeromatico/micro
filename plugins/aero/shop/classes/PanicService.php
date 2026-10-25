<?php namespace Aero\Shop\Classes;

use Aero\Shop\Models\Order;
use Aero\Shop\Models\PanicAlert;
use Event;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Botón de emergencia del comensal (página del pedido): crea un aviso con el número de mesa que la cocina y la caja
 * ven hasta que alguien lo atiende. Solo para pedidos «Comer en local»; limitado para que nadie lo repita en masa.
 */
class PanicService
{
    /** Segundos antes de poder avisar de nuevo desde el mismo pedido. */
    public const COOLDOWN = 120;

    public static function eligible(Order $o): bool
    {
        // El local puede apagar el botón en Configuración de la tienda (por defecto, encendido).
        $on = \Aero\Shop\Models\ShopSettings::where('tenant_id', $o->tenant_id)->first()?->restaurant()['panic_button'] ?? true;

        return $on && $o->order_type === 'dine_in' && !in_array($o->status, ['cancelled', 'refunded'], true) && $o->created_at && $o->created_at->gte(now()->subHours(8));
    }

    /** El aviso vigente de este pedido (sin atender, o atendido hace poco), para mostrarlo al cliente. */
    public static function current(Order $o): ?PanicAlert
    {
        return PanicAlert::forTenant((int) $o->tenant_id)->where('order_id', $o->id)->where('created_at', '>=', now()->subHours(PanicAlert::VISIBLE_HOURS))->latest('id')->first();
    }

    public static function label(Order $o): string
    {
        if ($o->table_label) {
            return 'Mesa ' . $o->table_label;
        }

        return 'Sin mesa · pedido ' . $o->short_number . ($o->customer?->first_name ? ' · ' . $o->customer->first_name : '');
    }

    /** @return array{0: ?PanicAlert, 1: string} [aviso, 'created'|'already'|'wait'|'limit'|'not_allowed'] */
    public static function raise(Order $o, ?string $ip): array
    {
        if (!static::eligible($o)) {
            return [null, 'not_allowed'];
        }

        $cur = static::current($o);
        if ($cur && !$cur->acknowledged_at) {
            return [$cur, 'already'];
        }
        if ($cur && $cur->created_at->gt(now()->subSeconds(self::COOLDOWN))) {
            return [$cur, 'wait'];
        }

        $key = 'shop-panic:' . ($ip ?: 'x');
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return [null, 'limit'];
        }
        RateLimiter::hit($key, 3600);

        $alert = PanicAlert::create([
            'tenant_id' => $o->tenant_id, 'order_id' => $o->id, 'table_label' => $o->table_label, 'label' => static::label($o), 'ip' => $ip,
        ]);
        Event::fire('aero.shop.panicRaised', [$alert, $o]);

        return [$alert, 'created'];
    }

    public static function active(int $tenantId)
    {
        return PanicAlert::forTenant($tenantId)->active()->with('order.customer')->orderBy('id')->get();
    }

    public static function acknowledge(int $tenantId, int $id, string $by): bool
    {
        return (bool) PanicAlert::forTenant($tenantId)->whereNull('acknowledged_at')->where('id', $id)
            ->update(['acknowledged_at' => now(), 'acknowledged_by' => mb_substr($by, 0, 80)]);
    }

    public static function present(PanicAlert $a): array
    {
        return [
            'id' => $a->id, 'label' => $a->label, 'table' => $a->table_label, 'order_id' => $a->order_id,
            'short' => $a->order?->short_number, 'customer' => $a->order?->customer?->first_name,
            'at' => $a->created_at?->toIso8601String(),
        ];
    }
}

