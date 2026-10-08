<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Seguimiento de cuánto de un regalo de plan con vencimiento (Plan.credits
 * mode=expiring, ver Aero.Sites) sigue sin gastarse. NO es parte del libro
 * mayor inmutable (ver LedgerGuard): es una tabla mutable de contabilidad
 * auxiliar. Credits::chargeRaw() descuenta de acá primero (el lote más
 * próximo a vencer primero); credits:expire-plan-grants postea el
 * remanente como una transacción kind=expiry real cuando vence.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('aero_credits_plan_grant_lots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('credit_type_id');
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->unsignedBigInteger('transaction_id')->nullable()->unique();
            $table->unsignedInteger('granted_amount');
            $table->unsignedInteger('remaining_amount');
            $table->timestamp('expires_at')->index();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'credit_type_id', 'expires_at'], 'credit_plan_grant_lots_consume_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('aero_credits_plan_grant_lots');
    }
};
