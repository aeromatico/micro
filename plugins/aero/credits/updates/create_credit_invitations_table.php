<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        Schema::create('aero_credits_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedInteger('invited_by_user_id')->nullable();
            $table->string('channel', 20)->default('whatsapp');
            $table->string('recipient');
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->string('period_unit', 20)->default('monthly');
            $table->unsignedInteger('period_count')->default(1);
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('redeemed_by_tenant_id')->nullable();
            $table->dateTime('redeemed_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('aero_credits_invitations');
    }
};
