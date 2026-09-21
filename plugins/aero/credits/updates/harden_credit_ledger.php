<?php

use Aero\Credits\Classes\LedgerGuard;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Libro mayor a prueba de errores: clasificación por tipo de movimiento
 * (kind), agrupación de patas de un intercambio (journal_id), acumulado diario
 * mantenido por la BD y las garantías de LedgerGuard (triggers + CHECK).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('aero_credits_transactions', function (Blueprint $table) {
            $table->string('kind', 20)->default('charge')->after('delta');
            $table->char('journal_id', 36)->nullable()->after('kind');
            $table->unsignedBigInteger('account_id')->nullable()->after('journal_id');

            $table->index(['account_id', 'id']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['kind', 'created_at']);
            $table->index('journal_id');
        });

        // Movimientos históricos (si los hubiera): kind por signo / reembolso.
        DB::statement("UPDATE aero_credits_transactions SET kind = CASE WHEN refund_of_id IS NOT NULL THEN 'refund' WHEN delta > 0 THEN 'adjust_in' ELSE 'charge' END");
        DB::statement('UPDATE aero_credits_transactions t JOIN aero_credits_accounts a ON a.tenant_id = t.tenant_id AND a.credit_type_id = t.credit_type_id SET t.account_id = a.id');

        Schema::create('aero_credits_daily', function (Blueprint $table) {
            $table->date('day');
            $table->unsignedInteger('tenant_id');
            $table->unsignedBigInteger('credit_type_id');
            $table->string('kind', 20);
            $table->string('action_code', 64)->default('');
            $table->unsignedBigInteger('entries')->default(0);
            $table->bigInteger('amount')->default(0);

            $table->primary(['day', 'tenant_id', 'credit_type_id', 'kind', 'action_code'], 'aero_credits_daily_pk');
            $table->index(['tenant_id', 'day']);
        });

        DB::statement("INSERT INTO aero_credits_daily (day, tenant_id, credit_type_id, kind, action_code, entries, amount)
            SELECT DATE(created_at), tenant_id, credit_type_id, kind, COALESCE(action_code, ''), COUNT(*), SUM(delta)
            FROM aero_credits_transactions GROUP BY DATE(created_at), tenant_id, credit_type_id, kind, COALESCE(action_code, '')");

        $pos = "'" . implode("','", LedgerGuard::POSITIVE_KINDS) . "'";
        $neg = "'" . implode("','", LedgerGuard::NEGATIVE_KINDS) . "'";

        DB::statement('ALTER TABLE aero_credits_accounts ADD CONSTRAINT aero_credits_accounts_balance_nonneg CHECK (balance >= 0)');
        DB::statement("ALTER TABLE aero_credits_transactions ADD CONSTRAINT aero_credits_tx_kind_sign CHECK (
            (delta > 0 AND kind IN ({$pos})) OR (delta < 0 AND kind IN ({$neg})) OR (delta = 0 AND kind = 'charge'))");

        LedgerGuard::install();
    }

    public function down()
    {
        LedgerGuard::dropTriggers();

        DB::statement('ALTER TABLE aero_credits_transactions DROP CONSTRAINT aero_credits_tx_kind_sign');
        DB::statement('ALTER TABLE aero_credits_accounts DROP CONSTRAINT aero_credits_accounts_balance_nonneg');
        Schema::dropIfExists('aero_credits_daily');

        Schema::table('aero_credits_transactions', function (Blueprint $table) {
            $table->dropIndex(['account_id', 'id']);
            $table->dropIndex(['tenant_id', 'created_at']);
            $table->dropIndex(['kind', 'created_at']);
            $table->dropIndex(['journal_id']);
            $table->dropColumn(['kind', 'journal_id', 'account_id']);
        });
    }
};
