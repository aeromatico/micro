<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        Schema::create('aero_credits_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tenant_id');
            $table->unsignedBigInteger('credit_type_id');
            $table->bigInteger('delta');
            $table->bigInteger('balance_after');
            $table->string('action_code')->nullable();
            $table->string('source_plugin')->nullable();
            $table->string('reason')->nullable();
            $table->text('meta')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['tenant_id', 'credit_type_id']);
            $table->index('action_code');
        });
    }

    public function down()
    {
        Schema::dropIfExists('aero_credits_transactions');
    }
};
