<?php namespace Aero\WpFlash\Classes;

use Event;
use Aero\WpFlash\Models\CustomerLink;

/**
 * Igual criterio que ProductSync: WooCommerce manda, se sobrescribe sin
 * preguntar. Deduplica por email contra clientes ya existentes en Aero.Shop
 * (mismo criterio que OrderService::resolveCustomer(), sin acoplarse a esa
 * clase) para no crear un duplicado del cliente que ya compró por el
 * checkout propio.
 */
class CustomerSync
{
    public static function handle(int $tenantId, string $topic, array $payload): void
    {
        if (!class_exists(\Aero\Shop\Models\Customer::class)) {
            return;
        }

        $wpCustomerId = $payload['id'] ?? null;
        if (!$wpCustomerId || empty($payload['email'])) {
            return;
        }

        static::upsert($tenantId, $payload);
    }

    protected static function upsert(int $tenantId, array $payload): void
    {
        $wpCustomerId = (int) $payload['id'];
        $email = $payload['email'];

        $link = CustomerLink::firstOrNew(['tenant_id' => $tenantId, 'wp_customer_id' => $wpCustomerId]);

        $customer = $link->shop_customer_id
            ? \Aero\Shop\Models\Customer::forTenant($tenantId)->find($link->shop_customer_id)
            : \Aero\Shop\Models\Customer::forTenant($tenantId)->where('email', $email)->first();

        $customer = $customer ?: new \Aero\Shop\Models\Customer(['tenant_id' => $tenantId, 'email' => $email]);

        $customer->email      = $email;
        $customer->first_name = $payload['first_name'] ?: $customer->first_name;
        $customer->last_name  = $payload['last_name'] ?: $customer->last_name;
        $customer->phone      = $payload['billing']['phone'] ?? $customer->phone;
        $customer->save();

        $link->fill([
            'shop_customer_id' => $customer->id,
            'email'            => $email,
            'last_synced_at'   => now(),
        ]);
        $link->save();

        Event::fire('aero.wpflash.customerSynced', [$customer]);
    }
}
