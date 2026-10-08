<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * "Regalar una suscripción": alguien (sin cuenta) paga un QR y, confirmado el
 * pago, se emite un cupón de un solo uso que se le manda al destinatario.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('aero_credits_gifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_id');
            $table->string('period_unit', 20)->default('monthly');
            $table->unsignedInteger('period_count')->default(1);
            $table->decimal('amount_bob', 12, 2)->default(0);
            $table->string('buyer_name')->nullable();
            $table->string('buyer_contact')->nullable();
            $table->string('recipient_channel', 20)->default('whatsapp');
            $table->string('recipient');
            $table->string('recipient_name')->nullable();
            $table->text('message')->nullable();
            $table->string('status', 20)->default('pending')->index(); // pending|paid|expired
            $table->unsignedBigInteger('qr_code_id')->nullable();
            $table->string('payment_reference')->nullable()->index();
            $table->unsignedBigInteger('coupon_id')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('aero_credits_gifts');
    }
};
