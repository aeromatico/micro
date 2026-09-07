<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Cuenta de aero/qrbo (opcional) usada para generar el QR de cobro que se
 * adjunta a los recordatorios de Cobranzas. Sin FK real: aero/qrbo es un
 * plugin opcional, igual que en aero/shop (ver
 * add_qrbo_bank_account_id_to_payment_gateways.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_crm_settings', function (Blueprint $table) {
            $table->unsignedInteger('collections_bank_account_id')->nullable()
                ->comment('Cuenta bancaria de aero/qrbo usada para generar el QR de cobro adjunto a los recordatorios.');
        });
    }

    public function down(): void
    {
        Schema::table('aero_crm_settings', function (Blueprint $table) {
            $table->dropColumn('collections_bank_account_id');
        });
    }
};
