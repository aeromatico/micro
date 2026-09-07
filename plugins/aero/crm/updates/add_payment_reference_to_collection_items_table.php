<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Referencia interna del QrCode de aero/qrbo generado para este cobro (su
 * `internal_reference`) — mismo patrón que `aero_shop_orders.payment_reference`.
 * Permite reconciliar el pago (ver Plugin::bootQrboPaymentBridge()) y
 * reutilizar el mismo QR mientras siga vigente en vez de generar uno nuevo
 * en cada recordatorio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_crm_collection_items', function (Blueprint $table) {
            $table->string('payment_reference')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('aero_crm_collection_items', function (Blueprint $table) {
            $table->dropColumn('payment_reference');
        });
    }
};
