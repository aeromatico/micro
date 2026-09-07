<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Campo decorativo desde siempre: nada fuera de PaymentGateway lo leía, y
 * su valor ya lo decidía el código solo (siempre true para 'manual', forzado
 * a false para 'pagos_qr' en beforeSave()). Con 'Pagos offline' pudiendo
 * llevar o no una cuenta QRBO, mantener un switch que no controlaba nada
 * real solo confundía.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_shop_payment_gateways', function (Blueprint $table) {
            $table->dropColumn('requires_manual_confirmation');
        });
    }

    public function down(): void
    {
        Schema::table('aero_shop_payment_gateways', function (Blueprint $table) {
            $table->boolean('requires_manual_confirmation')->default(true);
        });
    }
};
