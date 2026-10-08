<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Canjes de cupón por un tenant que YA existe (Mis invitaciones → Canjear
 * código): un tenant no puede canjear dos veces el mismo cupón.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('aero_credits_coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coupon_id');
            $table->unsignedBigInteger('tenant_id');
            $table->timestamps();

            $table->unique(['coupon_id', 'tenant_id'], 'credits_coupon_tenant_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('aero_credits_coupon_redemptions');
    }
};
