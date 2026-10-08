<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('aero_crm_quick_reply_account')) {
            return;
        }

        // Sin filas para una respuesta = vale para todas las cuentas Hello.
        Schema::create('aero_crm_quick_reply_account', function (Blueprint $table) {
            $table->unsignedBigInteger('quick_reply_id');
            $table->unsignedBigInteger('account_id');
            $table->primary(['quick_reply_id', 'account_id']);
            $table->index('account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_crm_quick_reply_account');
    }
};
