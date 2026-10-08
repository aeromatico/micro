<?php namespace Aero\Finance\Classes;

use Aero\Finance\Models\FinanceSettings;
use Aero\Finance\Models\Movement;

/**
 * Registro automático de lo que cobran Shop (también el POS: cada venta POS es
 * un pedido) y Gimnasio. Siempre crea un Movement (visible en la lista) con su
 * asiento; idempotente por (tenant, fuente). Nunca rompe a quien dispara el evento.
 */
class Listeners
{
    public static function register(): void
    {
        \Event::listen('aero.shop.orderPaid', fn ($order) => self::safely(fn () => self::shopPaid($order)));
        \Event::listen('aero.shop.orderRefunded', fn ($order) => self::safely(fn () => self::voidBySource('shop_order', $order->id, $order->tenant_id, 'Pedido reembolsado')));
        \Event::listen('aero.gym.membershipActivated', fn ($m) => self::safely(fn () => self::gymActivated($m)));
    }

    protected static function safely(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            \Log::warning('[finance] no se pudo registrar el asiento automático: ' . $e->getMessage());
        }
    }

    protected static function exists(int $tenantId, string $type, $id): bool
    {
        return Movement::where('tenant_id', $tenantId)->where('source_type', $type)->where('source_id', (string) $id)->exists();
    }

    protected static function shopPaid($order): void
    {
        $tenantId = (int) $order->tenant_id;
        if (!FinanceSettings::allows($tenantId, 'shop') || (float) $order->grand_total <= 0 || self::exists($tenantId, 'shop_order', $order->id)) {
            return;
        }

        AccountSeeder::ensure($tenantId);
        $driver = $order->payment_gateway?->driver;
        $currency = strtoupper($order->currency?->code ?: 'BOB');
        $snapshot = (float) $order->exchange_rate_snapshot;

        app(MovementService::class)->record($tenantId, [
            'kind'                => 'income',
            'date'                => ($order->paid_at ?: now())->toDateString(),
            'amount'              => round((float) $order->grand_total, 2),
            'currency'            => $currency,
            'exchange_rate'       => $currency !== 'BOB' && $snapshot > 0 && $snapshot != 1.0 ? $snapshot : null,
            'category_account_id' => AccountSeeder::system($tenantId, 'sales')->id,
            'cash_account_id'     => AccountSeeder::system($tenantId, $driver === 'pagos_qr' ? 'bank' : 'cash')->id,
            'description'         => 'Pedido ' . $order->order_number,
            'document_no'         => $order->order_number,
            'tax_amount'          => round((float) $order->tax_total, 2),
        ], ['type' => 'shop_order', 'id' => $order->id]);
    }

    protected static function gymActivated($m): void
    {
        $tenantId = (int) $m->tenant_id;
        if (!FinanceSettings::allows($tenantId, 'gym') || (float) $m->price <= 0 || !$m->paid_at || self::exists($tenantId, 'gym_membership', $m->id)) {
            return;
        }

        AccountSeeder::ensure($tenantId);
        $name = trim(($m->member?->name ?? '') . ' — ' . ($m->plan?->name ?? 'Membresía'), ' —');

        app(MovementService::class)->record($tenantId, [
            'kind'                => 'income',
            'date'                => $m->paid_at->toDateString(),
            'amount'              => round((float) $m->price, 2),
            'currency'            => $m->currency ?: 'BOB',
            'category_account_id' => AccountSeeder::system($tenantId, 'memberships')->id,
            'cash_account_id'     => AccountSeeder::system($tenantId, $m->payment_reference ? 'bank' : 'cash')->id,
            'description'         => 'Membresía ' . ($name ?: '#' . $m->id),
            'counterparty'        => $m->member?->name,
        ], ['type' => 'gym_membership', 'id' => $m->id]);
    }

    protected static function voidBySource(string $type, $id, $tenantId, string $reason): void
    {
        $mov = Movement::where('tenant_id', $tenantId)->where('source_type', $type)->where('source_id', (string) $id)
            ->where('status', 'posted')->first();
        if ($mov) {
            app(MovementService::class)->void($mov, $reason);
        }
    }
}
