<?php

use Aero\Credits\Classes\LedgerGuard;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Billetera de dinero (Bs): una "moneda" más del mismo libro mayor (is_money),
 * con sus propios tipos de movimiento (wallet_deposit / wallet_spend). Recibe
 * el sobrante de las recargas y sirve para comprar cualquier moneda.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('aero_credits_types', function (Blueprint $table) {
            $table->boolean('is_money')->default(false)->after('is_exchangeable');
        });

        Schema::table('aero_credits_purchases', function (Blueprint $table) {
            $table->bigInteger('wallet_units')->default(0)->after('amount_bob'); // Bs (en diezmilésimas) que fueron a la billetera
        });

        if (!DB::table('aero_credits_types')->where('is_money', true)->exists()) {
            DB::table('aero_credits_types')->insert([
                'code' => 'bs', 'label' => 'Saldo en Bs', 'color' => '#22c55e', 'usd_value' => 0, 'price_bob' => 0,
                'is_exchangeable' => false, 'is_money' => true, 'low_balance_threshold' => 0, 'is_active' => true,
                'sort_order' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $pos = "'" . implode("','", LedgerGuard::POSITIVE_KINDS) . "'";
        $neg = "'" . implode("','", LedgerGuard::NEGATIVE_KINDS) . "'";
        DB::statement('ALTER TABLE aero_credits_transactions DROP CONSTRAINT aero_credits_tx_kind_sign');
        DB::statement("ALTER TABLE aero_credits_transactions ADD CONSTRAINT aero_credits_tx_kind_sign CHECK (
            (delta > 0 AND kind IN ({$pos})) OR (delta < 0 AND kind IN ({$neg})) OR (delta = 0 AND kind = 'charge'))");
    }

    public function down()
    {
        DB::table('aero_credits_types')->where('is_money', true)->delete();
        Schema::table('aero_credits_purchases', fn (Blueprint $t) => $t->dropColumn('wallet_units'));
        Schema::table('aero_credits_types', fn (Blueprint $t) => $t->dropColumn('is_money'));
    }
};
