<?php namespace Aero\Credits\Classes;

use Aero\Credits\Classes\Exceptions\InsufficientCreditsException;
use Aero\Credits\Models\CreditAccount;
use Aero\Credits\Models\CreditAction;
use Aero\Credits\Models\CreditHold;
use Aero\Credits\Models\CreditTransaction;
use Aero\Credits\Models\CreditType;
use Aero\Credits\Models\Settings;
use BackendAuth;
use Cache;
use DB;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Throwable;

/**
 * Punto único de entrada al sistema de créditos. Cualquier plugin que quiera
 * cobrar (IA, llamadas de voz, conectores) pasa por acá — nunca toca los
 * modelos directamente.
 *
 * La contabilidad NO depende de este código: el saldo, balance_after, el
 * acumulado diario y las prohibiciones (negativos, editar/borrar movimientos,
 * tocar saldos a mano) las aplica la base de datos (ver LedgerGuard). Esta
 * clase solo inserta movimientos con su `kind` y traduce los errores.
 *
 * Ningún otro plugin es requerido por este. `resolveCurrentTenantId()` usa
 * Aero\Sites si está instalado, pero degrada a null sin romper nada.
 */
class Credits
{
    protected const LOW_BALANCE_ALERT_THROTTLE = 3600 * 12; // no repetir la misma alerta antes de 12h
    public const DEFAULT_HOLD_TTL = 900; // segundos antes de que un cobro sin resolver se reembolse solo

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

    /** ['bronce' => 1200, 'oro' => 30, ...] para todos los tipos activos. */
    public static function balances(int $tenantId): array
    {
        $byType = CreditAccount::where('tenant_id', $tenantId)->pluck('balance', 'credit_type_id');
        $result = [];

        foreach (CreditType::active()->get() as $type) {
            $result[$type->code] = (int) ($byType[$type->id] ?? 0);
        }

        return $result;
    }

    /**
     * Costo de una acción del catálogo. Acción desconocida = error de
     * configuración (excepción, no cobro silencioso). Acción existente pero
     * desactivada = gratis de forma explícita (amount 0).
     *
     * @throws \RuntimeException si el código no existe en el catálogo.
     */
    public static function cost(string $actionCode): array
    {
        $action = CreditAction::where('code', $actionCode)->first();

        if (!$action) {
            throw new \RuntimeException("Aero.Credits: la acción '{$actionCode}' no existe en el catálogo de acciones facturables.");
        }

        if (!$action->creditType) {
            throw new \RuntimeException("Aero.Credits: la acción '{$actionCode}' no tiene un tipo de crédito válido.");
        }

        return [
            'type'   => $action->creditType,
            'amount' => $action->is_active ? (int) $action->default_cost : 0,
        ];
    }

    public static function canAfford(int $tenantId, string $actionCode): bool
    {
        $cost = static::cost($actionCode);

        return static::balance($tenantId, $cost['type']->code) >= $cost['amount'];
    }

    public static function usdValue(int $credits, string $creditTypeCode): float
    {
        $type = CreditType::findByCode($creditTypeCode);

        return round($credits * (float) ($type->usd_value ?? 0), 4);
    }

    // -------------------------------------------------------------------
    // Escritura — todo pasa por post(), que solo INSERTA un movimiento
    // -------------------------------------------------------------------

    /**
     * Inserta UN movimiento. El trigger de la BD calcula balance_after,
     * actualiza la cuenta y el acumulado diario, y rechaza saldos negativos.
     *
     * @throws InsufficientCreditsException
     */
    protected static function post(int $tenantId, CreditType $type, int $delta, string $kind, array $attrs = []): CreditTransaction
    {
        try {
            $tx = CreditTransaction::create([
                'tenant_id'      => $tenantId,
                'credit_type_id' => $type->id,
                'delta'          => $delta,
                'kind'           => $kind,
                'balance_after'  => 0, // lo fija el trigger
            ] + $attrs);
        }
        catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'credits:insufficient_balance')) {
                throw new InsufficientCreditsException($type->code, abs($delta), static::balance($tenantId, $type->code));
            }

            throw $e;
        }

        return $tx->refresh(); // trae balance_after / account_id calculados por la BD
    }

    /**
     * @throws InsufficientCreditsException si el saldo no alcanza.
     */
    public static function charge(int $tenantId, string $actionCode, array $context = []): CreditTransaction
    {
        $cost = static::cost($actionCode);

        return static::chargeRaw($tenantId, $cost['type'], $cost['amount'], $actionCode, $context);
    }

    /**
     * Cobro directo sin pasar por el catálogo de CreditAction — para
     * integraciones que ya conocen su propio costo/color.
     *
     * @throws InsufficientCreditsException si el saldo no alcanza.
     */
    public static function chargeRaw(int $tenantId, CreditType $type, int $amount, string $actionCode, array $context = []): CreditTransaction
    {
        if ($amount < 0) {
            throw new \InvalidArgumentException("Aero.Credits: monto de cobro inválido ({$amount}).");
        }

        // Idempotencia: la misma clave nunca cobra dos veces (reintentos de job/webhook).
        $key = $context['idempotency_key'] ?? null;

        if ($key && $existing = CreditTransaction::where('idempotency_key', $key)->first()) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($tenantId, $type, $amount, $actionCode, $context, $key) {
                $tx = static::post($tenantId, $type, -$amount, 'charge', [
                    'action_code'        => $actionCode,
                    'source_plugin'      => $context['source_plugin'] ?? null,
                    'reason'             => $context['reason'] ?? $actionCode,
                    'meta'               => $context,
                    'idempotency_key'    => $key,
                    'created_by_user_id' => optional(BackendAuth::getUser())->id,
                ]);

                // context['hold_ttl'] (segundos): el cobro queda "pendiente" hasta
                // settle()/refund(); si nadie lo resuelve, sweepHolds() lo reembolsa.
                if (isset($context['hold_ttl']) && $amount > 0) {
                    CreditHold::create([
                        'transaction_id' => $tx->id,
                        'tenant_id'      => $tenantId,
                        'status'         => CreditHold::PENDING,
                        'expires_at'     => now()->addSeconds((int) $context['hold_ttl']),
                        'created_at'     => now(),
                    ]);
                }

                // Después del commit: si la transacción hace rollback no se
                // quema el throttle ni se avisa de un saldo que nunca existió.
                DB::afterCommit(fn () => static::maybeAlertLowBalance($tenantId, $type, $tx->balance_after));

                return $tx;
            });
        }
        catch (QueryException $e) {
            // Carrera: otro proceso insertó la misma idempotency_key entre el chequeo y el insert.
            if ($key && $existing = CreditTransaction::where('idempotency_key', $key)->first()) {
                return $existing;
            }

            throw $e;
        }
    }

    /**
     * Idempotente: reembolsar dos veces el mismo movimiento devuelve el
     * reembolso original (refund_of_id es único a nivel de BD).
     */
    public static function refund(CreditTransaction $tx, string $reason): CreditTransaction
    {
        if ($tx->delta >= 0) {
            throw new \InvalidArgumentException("Aero.Credits: el movimiento #{$tx->id} no es un cobro, no se puede reembolsar.");
        }

        if ($tx->kind !== 'charge') {
            throw new \InvalidArgumentException("Aero.Credits: solo se reembolsan cobros (movimiento #{$tx->id} es '{$tx->kind}').");
        }

        if ($existing = CreditTransaction::where('refund_of_id', $tx->id)->first()) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($tx, $reason) {
                $refund = static::post($tx->tenant_id, CreditType::findOrFail($tx->credit_type_id), abs($tx->delta), 'refund', [
                    'action_code'   => $tx->action_code,
                    'source_plugin' => $tx->source_plugin,
                    'reason'        => $reason,
                    'meta'          => ['refund_of' => $tx->id],
                    'refund_of_id'  => $tx->id,
                ]);

                CreditHold::where('transaction_id', $tx->id)->where('status', CreditHold::PENDING)
                    ->update(['status' => CreditHold::REFUNDED, 'resolved_at' => now()]);

                return $refund;
            });
        }
        catch (QueryException $e) {
            if ($existing = CreditTransaction::where('refund_of_id', $tx->id)->first()) {
                return $existing;
            }

            throw $e;
        }
    }

    /**
     * Entrada de monedas. $kind: purchase (venta), gift (regalo), plan_grant
     * (incluidas en un plan) o adjust_in (ajuste manual). Con $idempotencyKey,
     * repetir la recarga devuelve el movimiento original.
     */
    public static function topUp(int $tenantId, string $creditTypeCode, int $amount, string $reason, ?int $byUserId = null, ?string $idempotencyKey = null, string $kind = 'adjust_in', array $meta = []): CreditTransaction
    {
        if (!in_array($kind, ['purchase', 'gift', 'plan_grant', 'adjust_in'], true)) {
            throw new \InvalidArgumentException("Aero.Credits: tipo de recarga '{$kind}' no válido.");
        }

        if ($amount <= 0) {
            throw new \InvalidArgumentException("Aero.Credits: monto de recarga inválido ({$amount}).");
        }

        if ($idempotencyKey && $existing = CreditTransaction::where('idempotency_key', $idempotencyKey)->first()) {
            return $existing;
        }

        $type = CreditType::findByCode($creditTypeCode);

        if (!$type) {
            throw new \RuntimeException("Aero.Credits: tipo de crédito '{$creditTypeCode}' no existe.");
        }

        try {
            $tx = static::post($tenantId, $type, $amount, $kind, [
                'source_plugin'      => 'Aero.Credits',
                'reason'             => $reason,
                'meta'               => $meta ?: null,
                'idempotency_key'    => $idempotencyKey,
                'created_by_user_id' => $byUserId,
            ]);
        }
        catch (QueryException $e) {
            if ($idempotencyKey && $existing = CreditTransaction::where('idempotency_key', $idempotencyKey)->first()) {
                return $existing;
            }

            throw $e;
        }

        // Con saldo repuesto, la próxima vez que baje debe poder alertar de nuevo.
        if ($tx->balance_after > $type->low_balance_threshold) {
            Cache::forget("aero.credits.alert.credits.balance.low.{$tenantId}.{$type->id}");
            Cache::forget("aero.credits.alert.credits.balance.depleted.{$tenantId}.{$type->id}");
        }

        return $tx;
    }

    /** Ajuste manual con signo (superadmin): + suma (adjust_in), − resta (adjust_out). */
    public static function adjust(int $tenantId, string $creditTypeCode, int $signedAmount, string $reason, ?int $byUserId = null): CreditTransaction
    {
        if ($signedAmount === 0) {
            throw new \InvalidArgumentException('Aero.Credits: el ajuste no puede ser 0.');
        }

        if ($signedAmount > 0) {
            return static::topUp($tenantId, $creditTypeCode, $signedAmount, $reason, $byUserId, null, 'adjust_in');
        }

        $type = CreditType::findByCode($creditTypeCode);

        if (!$type) {
            throw new \RuntimeException("Aero.Credits: tipo de crédito '{$creditTypeCode}' no existe.");
        }

        return static::post($tenantId, $type, $signedAmount, 'adjust_out', [
            'source_plugin'      => 'Aero.Credits',
            'reason'             => $reason,
            'created_by_user_id' => $byUserId,
        ]);
    }

    // -------------------------------------------------------------------
    // Intercambio entre monedas (con comisión). Las no intercambiables (oro) nunca participan.
    // -------------------------------------------------------------------

    /**
     * Cálculo del intercambio, sin escribir nada (lo usa la UI en tiempo real
     * y exchange()). El valor se mide con price_bob de cada moneda.
     *
     * @return array{fee:int, net:int, received:int, price_from:float, price_to:float, fee_percent:float}
     */
    public static function exchangeQuote(CreditType $from, CreditType $to, int $amount): array
    {
        if ($from->id === $to->id) {
            throw new \InvalidArgumentException('Elige dos monedas distintas.');
        }

        foreach ([$from, $to] as $type) {
            if (!$type->is_exchangeable) {
                throw new \InvalidArgumentException("La moneda {$type->label} no se puede intercambiar.");
            }

            if ((float) $type->price_bob <= 0) {
                throw new \RuntimeException("Aero.Credits: la moneda {$type->code} no tiene precio en Bs configurado.");
            }
        }

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Indica cuántas monedas quieres intercambiar.');
        }

        $pct = Settings::exchangeFeePercent();
        $fee = (int) ceil($amount * $pct / 100);
        $net = $amount - $fee;
        $received = (int) floor($net * (float) $from->price_bob / (float) $to->price_bob);

        return [
            'fee'         => $fee,
            'net'         => $net,
            'received'    => $received,
            'price_from'  => (float) $from->price_bob,
            'price_to'    => (float) $to->price_bob,
            'fee_percent' => $pct,
        ];
    }

    /**
     * Intercambio atómico: 3 movimientos con el mismo journal_id (salida,
     * comisión, entrada) en una sola transacción — o se hacen todos o ninguno.
     *
     * @return array{journal_id:string, out:CreditTransaction, fee:CreditTransaction, in:CreditTransaction, quote:array}
     * @throws InsufficientCreditsException
     */
    public static function exchange(int $tenantId, string $fromCode, string $toCode, int $amount, ?int $byUserId = null): array
    {
        $from = CreditType::findByCode($fromCode);
        $to = CreditType::findByCode($toCode);

        if (!$from || !$to) {
            throw new \RuntimeException('Moneda de intercambio no existe.');
        }

        $quote = static::exchangeQuote($from, $to, $amount);

        if ($quote['received'] < 1) {
            throw new \InvalidArgumentException('Esa cantidad es muy pequeña: no alcanza para recibir al menos 1 moneda.');
        }

        $journal = (string) Str::uuid();
        $meta = ['journal' => $journal] + $quote + ['from' => $from->code, 'to' => $to->code, 'amount' => $amount];
        $common = ['source_plugin' => 'Aero.Credits', 'journal_id' => $journal, 'created_by_user_id' => $byUserId, 'meta' => $meta];

        return DB::transaction(function () use ($tenantId, $from, $to, $quote, $common, $journal) {
            $out = static::post($tenantId, $from, -$quote['net'], 'exchange_out', $common + ['reason' => "Intercambio {$from->label} → {$to->label}"]);
            $fee = static::post($tenantId, $from, -$quote['fee'], 'exchange_fee', $common + ['reason' => "Comisión {$quote['fee_percent']}% del intercambio"]);
            $in  = static::post($tenantId, $to, $quote['received'], 'exchange_in', $common + ['reason' => "Intercambio {$from->label} → {$to->label}"]);

            return ['journal_id' => $journal, 'out' => $out, 'fee' => $fee, 'in' => $in, 'quote' => $quote];
        });
    }

    // -------------------------------------------------------------------
    // Cobro con hold
    // -------------------------------------------------------------------

    /**
     * Confirma un cobro con hold: la acción sí se ejecutó, el cobro es firme.
     * Devuelve false si el hold ya estaba resuelto (p. ej. barrido antes).
     */
    public static function settle(CreditTransaction $tx): bool
    {
        return CreditHold::where('transaction_id', $tx->id)->where('status', CreditHold::PENDING)
            ->update(['status' => CreditHold::SETTLED, 'resolved_at' => now()]) > 0;
    }

    /**
     * Reembolsa los cobros pendientes vencidos: el proceso murió entre el
     * cobro y la ejecución (o nunca reportó el resultado).
     */
    public static function sweepHolds(): int
    {
        $count = 0;

        foreach (CreditHold::expired()->with('transaction')->limit(500)->get() as $hold) {
            if ($hold->transaction) {
                static::refund($hold->transaction, 'hold_vencido: la acción no reportó resultado');
                $count++;
            }
        }

        return $count;
    }

    /**
     * Azúcar: cobra (con hold), ejecuta $action(), y si truena reembolsa
     * automáticamente antes de relanzar la excepción original. Si el proceso
     * muere a mitad, sweepHolds() reembolsa el cobro.
     */
    public static function attempt(int $tenantId, string $actionCode, \Closure $action, array $context = []): mixed
    {
        $tx = static::charge($tenantId, $actionCode, $context + ['hold_ttl' => static::DEFAULT_HOLD_TTL]);

        try {
            $result = $action();
        }
        catch (Throwable $e) {
            static::refund($tx, 'error_ejecutando_accion: ' . $e->getMessage());
            throw $e;
        }

        static::settle($tx);

        return $result;
    }

    // -------------------------------------------------------------------
    // Contabilidad: resumen y verificación
    // -------------------------------------------------------------------

    /**
     * Resumen por moneda desde el acumulado diario (rápido a cualquier escala):
     * activas hoy entre todos los tenants, emitidas por origen, consumidas, etc.
     *
     * @return array<string, array>
     */
    public static function summary(?int $tenantId = null): array
    {
        $active = CreditAccount::query()->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->groupBy('credit_type_id')->selectRaw('credit_type_id, SUM(balance) as total')->pluck('total', 'credit_type_id');

        $byKind = DB::table('aero_credits_daily')->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->groupBy('credit_type_id', 'kind')->selectRaw('credit_type_id, kind, SUM(amount) as total')->get()
            ->groupBy('credit_type_id');

        $today = DB::table('aero_credits_daily')->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('day', now()->toDateString())->where('kind', 'charge')
            ->groupBy('credit_type_id')->selectRaw('credit_type_id, SUM(amount) as total')->pluck('total', 'credit_type_id');

        $out = [];

        foreach (CreditType::orderBy('sort_order')->get() as $type) {
            $k = ($byKind[$type->id] ?? collect())->pluck('total', 'kind');

            $out[$type->code] = [
                'label'          => $type->label,
                'color'          => $type->color,
                'active'         => (int) ($active[$type->id] ?? 0),
                'sold'           => (int) ($k['purchase'] ?? 0),
                'gifted'         => (int) ($k['gift'] ?? 0),
                'plan_grant'     => (int) ($k['plan_grant'] ?? 0),
                'adjust_in'      => (int) ($k['adjust_in'] ?? 0),
                'refunded'       => (int) ($k['refund'] ?? 0),
                'exchanged_in'   => (int) ($k['exchange_in'] ?? 0),
                'consumed'       => abs((int) ($k['charge'] ?? 0)),
                'exchanged_out'  => abs((int) ($k['exchange_out'] ?? 0)),
                'fees'           => abs((int) ($k['exchange_fee'] ?? 0)),
                'adjust_out'     => abs((int) ($k['adjust_out'] ?? 0)),
                'expired'        => abs((int) ($k['expiry'] ?? 0)),
                'consumed_today' => abs((int) ($today[$type->id] ?? 0)),
            ];
        }

        return $out;
    }

    /**
     * Compara el saldo de cada cuenta contra la verdad del ledger (SUM(delta)).
     * Con $fix corrige el saldo al valor del ledger (única vía autorizada a
     * saltarse la protección de saldos).
     *
     * @return array<int, array{account_id:int, tenant_id:int, type:string, cached:int, ledger:int, diff:int}>
     */
    public static function reconcile(?int $tenantId = null, bool $fix = false): array
    {
        $sums = CreditTransaction::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->groupBy('tenant_id', 'credit_type_id')
            ->selectRaw('tenant_id, credit_type_id, SUM(delta) as total')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->tenant_id . ':' . $r->credit_type_id => (int) $r->total]);

        $codes = CreditType::pluck('code', 'id');
        $issues = [];

        foreach (CreditAccount::query()->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->get() as $account) {
            $ledger = $sums[$account->tenant_id . ':' . $account->credit_type_id] ?? 0;

            if ((int) $account->balance === $ledger) {
                continue;
            }

            $issues[] = [
                'account_id' => $account->id,
                'tenant_id'  => $account->tenant_id,
                'type'       => $codes[$account->credit_type_id] ?? (string) $account->credit_type_id,
                'cached'     => (int) $account->balance,
                'ledger'     => $ledger,
                'diff'       => (int) $account->balance - $ledger,
            ];

            if ($fix) {
                LedgerGuard::withBalanceBypass(fn () => DB::table('aero_credits_accounts')->where('id', $account->id)->update(['balance' => $ledger]));
            }
        }

        return $issues;
    }

    /**
     * Auditoría completa de invariantes. Devuelve la lista de problemas
     * (vacía = contabilidad perfecta):
     *  1. saldo de cada cuenta = SUM(delta) del ledger
     *  2. cadena de balance_after coherente (saldo corrido) en cada cuenta
     *  3. acumulado diario = ledger (monto y cantidad de movimientos)
     *  4. ningún saldo negativo
     *  5. cada intercambio tiene exactamente sus 3 patas
     *  6. cada reembolso compensa exactamente su cobro original
     *
     * @return string[]
     */
    public static function verify(): array
    {
        $problems = [];

        foreach (static::reconcile() as $i) {
            $problems[] = "Saldo descuadrado: tenant {$i['tenant_id']} {$i['type']} — cuenta {$i['cached']}, ledger {$i['ledger']}.";
        }

        $broken = DB::selectOne('SELECT COUNT(*) c FROM (
            SELECT balance_after, SUM(delta) OVER (PARTITION BY account_id ORDER BY id) run FROM aero_credits_transactions
        ) x WHERE balance_after <> run')->c;
        if ($broken) {
            $problems[] = "{$broken} movimiento(s) con balance_after incoherente con el saldo corrido.";
        }

        $daily = DB::selectOne('SELECT
            (SELECT COALESCE(SUM(amount),0) FROM aero_credits_daily) da, (SELECT COALESCE(SUM(delta),0) FROM aero_credits_transactions) ta,
            (SELECT COALESCE(SUM(entries),0) FROM aero_credits_daily) de, (SELECT COUNT(*) FROM aero_credits_transactions) te');
        if ((int) $daily->da !== (int) $daily->ta || (int) $daily->de !== (int) $daily->te) {
            $problems[] = "Acumulado diario no cuadra con el ledger (monto {$daily->da} vs {$daily->ta}, movimientos {$daily->de} vs {$daily->te}).";
        }

        if ($neg = CreditAccount::where('balance', '<', 0)->count()) {
            $problems[] = "{$neg} cuenta(s) con saldo negativo.";
        }

        $badJournals = DB::select("SELECT journal_id FROM aero_credits_transactions WHERE journal_id IS NOT NULL
            GROUP BY journal_id HAVING SUM(kind = 'exchange_out') <> 1 OR SUM(kind = 'exchange_fee') <> 1 OR SUM(kind = 'exchange_in') <> 1 OR COUNT(*) <> 3");
        foreach ($badJournals as $j) {
            $problems[] = "Intercambio {$j->journal_id} incompleto (no tiene exactamente salida + comisión + entrada).";
        }

        $badRefunds = DB::selectOne("SELECT COUNT(*) c FROM aero_credits_transactions r
            LEFT JOIN aero_credits_transactions o ON o.id = r.refund_of_id
            WHERE r.kind = 'refund' AND (o.id IS NULL OR o.kind <> 'charge' OR o.account_id <> r.account_id OR o.delta + r.delta <> 0)")->c;
        if ($badRefunds) {
            $problems[] = "{$badRefunds} reembolso(s) que no compensan exactamente a su cobro original.";
        }

        return $problems;
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
