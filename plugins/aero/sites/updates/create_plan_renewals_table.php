<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Un registro por ciclo de cobro de renovación (mensual/anual) generado por
 * aero.sites:generate-renewals. `cycle_due_at` es el plan_expires_at del
 * tenant al momento de generar — el índice único con tenant_id evita
 * duplicar el cobro de un mismo ciclo aunque el comando corra dos veces.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('aero_sites_plan_renewals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('plan_id');
            $table->string('period', 16);
            $table->timestamp('cycle_due_at')->index();
            $table->string('status', 16)->default('pending')->index();
            $table->unsignedBigInteger('qr_code_id')->nullable();
            $table->string('payment_reference')->nullable()->unique();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 8)->default('BOB');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'cycle_due_at']);
            $table->foreign('tenant_id')->references('id')->on('aero_sites_tenants')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('aero_sites_plan_renewals');
    }
};
