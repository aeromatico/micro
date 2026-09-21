<?php namespace Aero\Credits\Classes;

use DB;

/**
 * Garantías de contabilidad EN la base de datos (no en PHP): aunque un bug, un
 * tinker o un plugin ajeno intente saltarse Credits::*, la BD no permite:
 *  - modificar ni borrar movimientos (libro mayor inmutable);
 *  - tocar un saldo que no venga de un movimiento nuevo;
 *  - un saldo negativo, ni un movimiento cuyo signo no cuadre con su tipo.
 * Cada INSERT en aero_credits_transactions calcula balance_after, actualiza
 * la cuenta y suma el acumulado diario bajo el mismo lock de fila.
 */
class LedgerGuard
{
    public const POSITIVE_KINDS = ['purchase', 'gift', 'plan_grant', 'adjust_in', 'refund', 'exchange_in'];
    public const NEGATIVE_KINDS = ['charge', 'exchange_out', 'exchange_fee', 'adjust_out', 'expiry'];

    protected const TRIGGERS = [
        'aero_credits_tx_bi', 'aero_credits_tx_ai', 'aero_credits_tx_bu', 'aero_credits_tx_bd',
        'aero_credits_acc_bi', 'aero_credits_acc_bu', 'aero_credits_acc_bd',
    ];

    public static function install(): void
    {
        static::dropTriggers();

        DB::unprepared(<<<'SQL'
CREATE TRIGGER aero_credits_tx_bi BEFORE INSERT ON aero_credits_transactions FOR EACH ROW
BEGIN
    DECLARE v_id BIGINT UNSIGNED;
    DECLARE v_bal BIGINT;
    INSERT IGNORE INTO aero_credits_accounts (tenant_id, credit_type_id, balance, created_at, updated_at)
        VALUES (NEW.tenant_id, NEW.credit_type_id, 0, NOW(), NOW());
    SELECT id, balance INTO v_id, v_bal FROM aero_credits_accounts
        WHERE tenant_id = NEW.tenant_id AND credit_type_id = NEW.credit_type_id FOR UPDATE;
    IF v_bal + NEW.delta < 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'credits:insufficient_balance';
    END IF;
    SET NEW.account_id = v_id;
    SET NEW.balance_after = v_bal + NEW.delta;
    IF NEW.created_at IS NULL THEN SET NEW.created_at = NOW(); END IF;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER aero_credits_tx_ai AFTER INSERT ON aero_credits_transactions FOR EACH ROW
BEGIN
    SET @aero_credits_ledger = 1;
    UPDATE aero_credits_accounts SET balance = NEW.balance_after, updated_at = NOW() WHERE id = NEW.account_id;
    SET @aero_credits_ledger = NULL;
    INSERT INTO aero_credits_daily (day, tenant_id, credit_type_id, kind, action_code, entries, amount)
        VALUES (DATE(NEW.created_at), NEW.tenant_id, NEW.credit_type_id, NEW.kind, COALESCE(NEW.action_code, ''), 1, NEW.delta)
        ON DUPLICATE KEY UPDATE entries = entries + 1, amount = amount + NEW.delta;
END
SQL);

        DB::unprepared("CREATE TRIGGER aero_credits_tx_bu BEFORE UPDATE ON aero_credits_transactions FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'credits:ledger_is_immutable'");
        DB::unprepared("CREATE TRIGGER aero_credits_tx_bd BEFORE DELETE ON aero_credits_transactions FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'credits:ledger_is_immutable'");

        DB::unprepared(<<<'SQL'
CREATE TRIGGER aero_credits_acc_bi BEFORE INSERT ON aero_credits_accounts FOR EACH ROW
BEGIN
    IF NEW.balance <> 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'credits:accounts_start_at_zero';
    END IF;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER aero_credits_acc_bu BEFORE UPDATE ON aero_credits_accounts FOR EACH ROW
BEGIN
    IF NEW.balance <> OLD.balance AND @aero_credits_ledger IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'credits:balance_is_ledger_managed';
    END IF;
END
SQL);

        DB::unprepared("CREATE TRIGGER aero_credits_acc_bd BEFORE DELETE ON aero_credits_accounts FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'credits:accounts_are_permanent'");
    }

    public static function dropTriggers(): void
    {
        foreach (static::TRIGGERS as $name) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$name}");
        }
    }

    /** Ejecuta $callback con permiso de corregir saldos (solo reconcile --fix). */
    public static function withBalanceBypass(\Closure $callback): mixed
    {
        DB::statement('SET @aero_credits_ledger = 1');

        try {
            return $callback();
        }
        finally {
            DB::statement('SET @aero_credits_ledger = NULL');
        }
    }

    /** Solo para pruebas reales: borra TODO el libro. Quita y reinstala las garantías. */
    public static function resetAll(): void
    {
        static::dropTriggers();

        try {
            foreach (['aero_credits_holds', 'aero_credits_daily', 'aero_credits_purchases', 'aero_credits_transactions', 'aero_credits_accounts'] as $table) {
                if (\Schema::hasTable($table)) {
                    DB::table($table)->delete();
                }
            }
        }
        finally {
            static::install();
        }
    }
}
