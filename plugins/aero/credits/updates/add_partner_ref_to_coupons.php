<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Origen externo de un cupón emitido por API (p. ej. un cliente con productos
 * activos en otra instalación): (source, external_ref) es único, así que pedir
 * dos veces el Trial de un mismo cliente devuelve el mismo cupón (idempotente).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('aero_credits_coupons', function (Blueprint $table) {
            $table->string('source', 60)->nullable()->after('note');
            $table->string('external_ref', 120)->nullable()->after('source');

            $table->unique(['source', 'external_ref'], 'credits_coupon_partner_ref_unique');
        });
    }

    public function down()
    {
        Schema::table('aero_credits_coupons', function (Blueprint $table) {
            $table->dropUnique('credits_coupon_partner_ref_unique');
            $table->dropColumn(['source', 'external_ref']);
        });
    }
};
