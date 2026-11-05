<?php namespace Aero\Tickets\Classes\Providers;

use Aero\Tickets\Models\Order;

/** Proveedores de pago en línea disponibles. Otros plugins suman los suyos con `aero.tickets.registerPaymentProviders`. */
class Registry
{
    /** @return PaymentProvider[] key => instancia */
    public static function all(): array
    {
        $classes = [PayQrProvider::class];
        foreach ((array) \Event::fire('aero.tickets.registerPaymentProviders') as $extra) {
            $classes = array_merge($classes, (array) $extra);
        }
        $out = [];
        foreach (array_unique($classes) as $c) {
            if (is_string($c) && class_exists($c) && is_subclass_of($c, PaymentProvider::class)) {
                $p = new $c;
                $out[$p->key()] = $p;
            }
        }

        return $out;
    }

    public static function forMethod(string $method): ?PaymentProvider
    {
        foreach (self::all() as $p) {
            if ($p->method() === $method) {
                return $p;
            }
        }

        return null;
    }

    public static function byKey(?string $key): ?PaymentProvider
    {
        return $key ? (self::all()[$key] ?? null) : null;
    }
}
