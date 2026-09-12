<?php namespace Aero\Credits\Classes;

use Aero\Credits\Classes\Exceptions\InsufficientCreditsException;
use Aero\Credits\Models\CreditAccount;
use Aero\Credits\Models\CreditAction;
use Aero\Credits\Models\CreditTransaction;
use Aero\Credits\Models\CreditType;
use Aero\Credits\Models\Settings;
use BackendAuth;
use Cache;
use DB;
use Throwable;

/**
 * Punto único de entrada al sistema de créditos. Cualquier plugin que quiera
 * cobrar (IA, llamadas de voz, conectores) pasa por acá — nunca toca los
 * modelos directamente — para que el bloqueo de saldo, el ledger y las
 * alertas de saldo bajo se apliquen siempre igual.
 *
 * Ningún otro plugin es requerido por este. `resolveCurrentTenantId()` usa
 * Aero\Sites si está instalado (mismo trait que ya usa Aero.Sites consigo
 * mismo para responder a Hello/Api), pero degrada a null sin romper nada en
 * una instalación single-tenant.
 */
class Credits
{
    protected const LOW_BALANCE_ALERT_THROTTLE = 3600 * 12; // no repetir la misma alerta antes de 12h

    // -------------------------------------------------------------------
    // Resolución de tenant
    // -------------------------------------------------------------------

    public static function resolveCurrentTenantId(): ?int
    {
        if (!class_exists(\Aero\Sites\Models\Tenant::class)) {
            return null;
        }

        return (new class {
            use \Aero\Sites\Traits\ResolvesCurrentTenant;
            public function id(): ?int
            {
                return $this->getCurrentTenantId();
            }
        })->id();
    }

    // -------------------------------------------------------------------
    // Lectura
    // -------------------------------------------------------------------

    public static function balance(int $tenantId, string $creditTypeCode): int
    {
        $type = CreditType::findByCode($creditTypeCode);

        if (!$type) {
            return 0;
        }

        return (int) CreditAccount::where('tenant_id', $tenantId)
            ->where('credit_type_id', $type->id)
            ->value('balance');
    }

    /** ['azul' => 1200, 'rojo' => 30, ...] para todos los tipos activos. */
    public static function balances(int $tenantId): array
    {
        $result = [];

        foreach (CreditType::active()->get() as $type) {
            $account = CreditAccount::where('tenant_id', $tenantId)->where('credit_type_id', $type->id)->first();
            $result[$type->code] = $account?->balance ?? 0;
        }

        return $result;
    }

    public static function cost(string $actionCode): array
    {
        $action = CreditAction::findActive($actionCode);

        if (!$action || !$action->creditType) {
            return ['type' => CreditType::active()->first(), 'amount' => 1];
        }

        return ['type' => $action->creditType, 'amount' => $action->default_cost];
    }

    public static function canAfford(int $tenantId, string $actionCode): bool
    {
        $cost = static::cost($actionCode);

        if (!$cost['type']) {
            return true; // sin tipos configurados, no hay nada que bloquear
        }

        return static::balance($tenantId, $cost['type']->code) >= $cost['amount'];
    }

    public static function usdValue(int $credits, string $creditTypeCode): float
    {
        $type = CreditType::findByCode($creditTypeCode);

        return round($credits * (float) ($type->usd_value ?? 0), 4);
    }

    // -------------------------------------------------------------------
    // Escritura
    // -------------------------------------------------------------------

    /**
     * @throws InsufficientCreditsException si el saldo no alcanza y el bloqueo está activo.
     */
    public static function charge(int $tenantId, string $actionCode, array $context = []): CreditTransaction
    {
        $cost = static::cost($actionCode);
        $type = $cost['type'];

        if (!$type) {
            throw new \RuntimeException('Aero.Credits: no hay ningún CreditType activo configurado.');
        }

        return static::chargeRaw($tenantId, $type, $cost['amount'], $actionCode, $context);
    }

    /**
     * Cobro directo sin pasar por el catálogo de CreditAction — para
     * integraciones que ya conocen su propio costo/color, como un
     * `Connector` con `credit_cost`/`credit_type_id` configurados en su
     * propio form (ver Plugin::bootConnectorIntegration()).
     *
     * @throws InsufficientCreditsException si el saldo no alcanza y el bloqueo está activo.
     */
    public static function chargeRaw(int $tenantId, CreditType $type, int $amount, string $actionCode, array $context = []): CreditTransaction
    {
        return DB::transaction(function () use ($tenantId, $type, $amount, $actionCode, $context) {
            $account = CreditAccount::where('tenant_id', $tenantId)
                ->where('credit_type_id', $type->id)
                ->lockForUpdate()
                ->first();

            if (!$account) {
                $account = CreditAccount::forTenant($tenantId, $type->id);
                $account = CreditAccount::where('id', $account->id)->lockForUpdate()->first();
            }

            if ($account->balance < $amount && Settings::blocksOnInsufficientBalance()) {
                throw new InsufficientCreditsException($type->code, $amount, $account->balance);
            }

            $account->balance -= $amount;
            $account->save();

            $tx = CreditTransaction::create([
                'tenant_id'      => $tenantId,
                'credit_type_id' => $type->id,
                'delta'          => -$amount,
                'balance_after'  => $account->balance,
                'action_code'    => $actionCode,
                'source_plugin'  => $context['source_plugin'] ?? null,
                'reason'         => $context['reason'] ?? $actionCode,
                'meta'           => $context,
                'created_by_user_id' => optional(BackendAuth::getUser())->id,
            ]);

            static::maybeAlertLowBalance($tenantId, $type, $account->balance);

            return $tx;
        });
    }

    public static function refund(CreditTransaction $tx, string $reason): CreditTransaction
    {
        $amount = abs($tx->delta);

        return DB::transaction(function () use ($tx, $amount, $reason) {
            $account = CreditAccount::where('tenant_id', $tx->tenant_id)
                ->where('credit_type_id', $tx->credit_type_id)
                ->lockForUpdate()
                ->firstOrFail();

            $account->balance += $amount;
            $account->save();

            return CreditTransaction::create([
                'tenant_id'      => $tx->tenant_id,
                'credit_type_id' => $tx->credit_type_id,
                'delta'          => $amount,
                'balance_after'  => $account->balance,
                'action_code'    => $tx->action_code,
                'source_plugin'  => $tx->source_plugin,
                'reason'         => $reason,
                'meta'           => ['refund_of' => $tx->id],
            ]);
        });
    }

    public static function topUp(int $tenantId, string $creditTypeCode, int $amount, string $reason, ?int $byUserId = null): CreditTransaction
    {
        $type = CreditType::findByCode($creditTypeCode);

        if (!$type) {
            throw new \RuntimeException("Aero.Credits: tipo de crédito '{$creditTypeCode}' no existe.");
        }

        return DB::transaction(function () use ($tenantId, $type, $amount, $reason, $byUserId) {
            $account = CreditAccount::where('tenant_id', $tenantId)
                ->where('credit_type_id', $type->id)
                ->lockForUpdate()
                ->first();

            if (!$account) {
                $account = CreditAccount::forTenant($tenantId, $type->id);
                $account = CreditAccount::where('id', $account->id)->lockForUpdate()->first();
            }

            $account->balance += $amount;
            $account->save();

            return CreditTransaction::create([
                'tenant_id'          => $tenantId,
                'credit_type_id'     => $type->id,
                'delta'              => $amount,
                'balance_after'      => $account->balance,
                'action_code'        => null,
                'source_plugin'      => 'Aero.Credits',
                'reason'             => $reason,
                'created_by_user_id' => $byUserId,
            ]);
        });
    }

    /**
     * Azúcar: cobra, ejecuta $action(), y si truena reembolsa automáticamente
     * antes de relanzar la excepción original. Así cualquier integración
     * nueva (formulario, IA, conector) queda protegida con una sola línea.
     */
    public static function attempt(int $tenantId, string $actionCode, \Closure $action, array $context = []): mixed
    {
        $tx = static::charge($tenantId, $actionCode, $context);

        try {
            return $action();
        }
        catch (Throwable $e) {
            static::refund($tx, 'error_ejecutando_accion: ' . $e->getMessage());
            throw $e;
        }
    }

    // -------------------------------------------------------------------
    // Alertas (soft dependency en Aero.Notify)
    // -------------------------------------------------------------------

    protected static function maybeAlertLowBalance(int $tenantId, CreditType $type, int $balance): void
    {
        if (!class_exists(\Aero\Notify\Classes\Notify::class)) {
            return;
        }

        $eventCode = null;

        if ($balance <= 0) {
            $eventCode = 'credits.balance.depleted';
        }
        elseif ($balance <= $type->low_balance_threshold) {
            $eventCode = 'credits.balance.low';
        }

        if (!$eventCode) {
            return;
        }

        $throttleKey = "aero.credits.alert.{$eventCode}.{$tenantId}.{$type->id}";

        if (Cache::has($throttleKey)) {
            return;
        }

        Cache::put($throttleKey, true, static::LOW_BALANCE_ALERT_THROTTLE);

        \Aero\Notify\Classes\Notify::fire($eventCode, [
            'credit_type'  => $type->label,
            'credit_color' => $type->code,
            'balance'      => $balance,
            'threshold'    => $type->low_balance_threshold,
        ], ['tenant_id' => $tenantId]);
    }
}
