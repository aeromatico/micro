<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_shop_payment_gateways', function (Blueprint $table) {
            $table->unsignedInteger('qrbo_bank_account_id')->nullable()->after('driver')
                ->comment('Solo driver pagos_qr: cuenta bancaria de aero/qrbo usada para emitir el QR dinámico.');
        });
    }

    public function down(): void
    {
        Schema::table('aero_shop_payment_gateways', function (Blueprint $table) {
            $table->dropColumn('qrbo_bank_account_id');
        });
    }
};
