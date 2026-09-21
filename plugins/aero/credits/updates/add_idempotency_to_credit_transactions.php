<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Candados a nivel de BD contra doble cobro / doble reembolso:
 *  - idempotency_key: un reintento con la misma clave devuelve el movimiento original.
 *  - refund_of_id: un movimiento solo puede ser reembolsado una vez.
 * Los reembolsos históricos guardaban el id en meta.refund_of; se rellenan acá.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('aero_credits_transactions', function (Blueprint $table) {
            $table->string('idempotency_key', 191)->nullable()->unique();
            $table->unsignedBigInteger('refund_of_id')->nullable()->unique();
        });

        foreach (DB::table('aero_credits_transactions')->where('delta', '>', 0)->whereNotNull('meta')->get(['id', 'meta']) as $row) {
            $meta = json_decode($row->meta, true);
            $original = $meta['refund_of'] ?? null;

            if ($original && !DB::table('aero_credits_transactions')->where('refund_of_id', $original)->exists()) {
                DB::table('aero_credits_transactions')->where('id', $row->id)->update(['refund_of_id' => $original]);
            }
        }
    }

    public function down()
    {
        Schema::table('aero_credits_transactions', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropUnique(['refund_of_id']);
            $table->dropColumn(['idempotency_key', 'refund_of_id']);
        });
    }
};
