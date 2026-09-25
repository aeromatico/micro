<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        Schema::create('aero_credits_invitation_quotas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->unique();
            $table->unsignedInteger('invites_allowed')->default(3);
            $table->unsignedBigInteger('grant_plan_id')->nullable();
            $table->string('grant_period_unit', 20)->nullable();
            $table->unsignedInteger('grant_period_count')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('aero_credits_invitation_quotas');
    }
};
