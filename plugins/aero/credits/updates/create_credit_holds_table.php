<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Estado mutable de cobros "a medias" (cobrado pero la acción aún no
 * terminó). El ledger sigue inmutable: acá solo se marca pending → settled |
 * refunded. Un barrido reembolsa los pending vencidos (proceso muerto).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('aero_credits_holds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id')->unique();
            $table->unsignedInteger('tenant_id');
            $table->string('status', 16)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['status', 'expires_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('aero_credits_holds');
    }
};
