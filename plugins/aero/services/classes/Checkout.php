<?php namespace Aero\Services\Classes;

use Aero\Credits\Classes\Credits;
use Aero\Credits\Classes\Money;
use Aero\Credits\Models\CreditType;
use Aero\Services\Models\Service;
use Aero\Services\Models\ServicePurchase;

/**
 * Punto único para comprar un plan de servicio. Todo el cobro pasa por
 * Aero.Credits (soft dependency, como el resto de la plataforma): en modo
 * "créditos" descuenta del color del plan; en modo "dinero" descuenta del
 * saldo en Bs del tenant (la misma billetera de Aero.Credits, ver Money) —
 * nunca genera un QR nuevo, así que si el saldo no alcanza la compra
 * simplemente falla y el tenant debe recargar antes (Wallet).
 *
 * No ejecuta el servicio: solo cobra y dEja la compra en `pending` para que
 * el equipo la entregue manualmente (Purchases, backend superadmin) — estos
 * son servicios humanos (ecommerce, marketing), no algo que se aprovisiona solo.
 */
class Checkout
{
    public static function available(): bool
    {
        return class_exists(Credits::class);
    }

    /** El plan por su posición en Service.plans, o null si ya no existe. */
    public static function plan(Service $service, int $planIndex): ?array
    {
        return collect((array) $service->plans)->get($planIndex);
    }

    /** Métodos de pago que admite un plan, según su modalidad de cobro. */
    public static function methodsFor(array $plan): array
    {
        if (($plan['type'] ?? null) === 'free') {
            return ['free'];
        }

        return match ($plan['pricing_mode'] ?? 'money') {
            'credits' => ['credits'],
            'both'    => ['credits', 'money'],
            default   => ['money'],
        };
    }

    /**
     * Costo del plan en el método elegido.
     *
     * @return array{amount:int, type:?CreditType}
     * @throws \InvalidArgumentException si el plan no tiene precio en ese método.
     */
    public static function cost(array $plan, string $method): array
    {
        if ($method === 'credits') {
            $type = filled($plan['credit_type'] ?? null) ? CreditType::findByCode((string) $plan['credit_type']) : null;

            if (!$type || !filled($plan['credit_price'] ?? null) || (int) $plan['credit_price'] < 1) {
                throw new \InvalidArgumentException('Este plan no tiene un precio en créditos configurado.');
            }

            return ['amount' => (int) $plan['credit_price'], 'type' => $type];
        }

        if ($method === 'money') {
            if (!filled($plan['price'] ?? null)) {
                throw new \InvalidArgumentException('Este plan es "a cotizar": contáctanos para cerrar el precio antes de comprarlo.');
            }

            $bob = (float) $plan['price'] + (float) ($plan['setup_fee'] ?? 0);

            return ['amount' => Money::units($bob), 'type' => CreditType::money()];
        }

        throw new \InvalidArgumentException('Método de pago no válido.');
    }

    /**
     * Cobra el plan y registra la compra. La compra queda `pending`: el
     * equipo la marca `fulfilled` desde el backend una vez entregado el
     * servicio (Checkout no sabe cómo se entrega cada servicio).
     *
     * @throws \Aero\Credits\Classes\Exceptions\InsufficientCreditsException si el saldo no alcanza.
     * @throws \InvalidArgumentException|\RuntimeException si el plan/método no es válido.
     */
    public static function purchase(Service $service, int $planIndex, int $tenantId, string $method, ?int $userId = null): ServicePurchase
    {
        if (!static::available()) {
            throw new \RuntimeException('Aero.Credits no está instalado: no se puede cobrar.');
        }

        $plan = static::plan($service, $planIndex);

        if (!$plan) {
            throw new \InvalidArgumentException('Ese plan ya no está disponible.');
        }

        if (!in_array($method, static::methodsFor($plan), true)) {
            throw new \InvalidArgumentException('Ese plan no admite ese método de pago.');
        }

        if ($method === 'free') {
            return ServicePurchase::create([
                'tenant_id'      => $tenantId,
                'service_id'     => $service->id,
                'plan_index'     => $planIndex,
                'plan_snapshot'  => $plan,
                'payment_method' => 'free',
                'amount'         => 0,
                'status'         => ServicePurchase::PENDING,
                'requested_by'   => $userId,
            ]);
        }

        $cost = static::cost($plan, $method);

        if (!$cost['type']) {
            throw new \RuntimeException('Falta configurar la moneda de cobro de este plan.');
        }

        $tx = Credits::chargeRaw($tenantId, $cost['type'], $cost['amount'], "services.purchase.{$service->slug}", [
            'source_plugin' => 'Aero.Services',
            'reason'        => "Compra: {$service->name} — {$plan['name']}",
            'service_id'    => $service->id,
            'plan_index'    => $planIndex,
        ]);

        return ServicePurchase::create([
            'tenant_id'             => $tenantId,
            'service_id'            => $service->id,
            'plan_index'            => $planIndex,
            'plan_snapshot'         => $plan,
            'payment_method'        => $method,
            'credit_type_code'      => $cost['type']->code,
            'amount'                => $cost['amount'],
            'credit_transaction_id' => $tx->id,
            'status'                => ServicePurchase::PENDING,
            'requested_by'          => $userId,
        ]);
    }

    /** Cancela una compra pendiente y reembolsa el cobro (si lo hubo). */
    public static function cancel(ServicePurchase $purchase, string $reason): void
    {
        if ($purchase->status !== ServicePurchase::PENDING) {
            throw new \InvalidArgumentException('Solo se puede cancelar una compra pendiente.');
        }

        if ($purchase->credit_transaction_id && static::available()) {
            $tx = \Aero\Credits\Models\CreditTransaction::find($purchase->credit_transaction_id);

            if ($tx) {
                Credits::refund($tx, $reason);
            }
        }

        $purchase->status = ServicePurchase::CANCELLED;
        $purchase->cancelled_at = now();
        $purchase->notes = trim(($purchase->notes ? $purchase->notes . "\n" : '') . "Cancelado: {$reason}");
        $purchase->save();
    }

    /** Marca la compra como entregada (el equipo ya hizo el trabajo). */
    public static function fulfill(ServicePurchase $purchase, ?int $byUserId = null, ?string $note = null): void
    {
        if ($purchase->status !== ServicePurchase::PENDING) {
            throw new \InvalidArgumentException('Solo se puede completar una compra pendiente.');
        }

        $purchase->status = ServicePurchase::FULFILLED;
        $purchase->fulfilled_at = now();
        $purchase->fulfilled_by = $byUserId;
        $purchase->notes = trim(($purchase->notes ? $purchase->notes . "\n" : '') . ($note ?: ''));
        $purchase->save();
    }
}
