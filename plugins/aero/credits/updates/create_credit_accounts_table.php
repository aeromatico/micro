<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        Schema::create('aero_credits_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tenant_id');
            $table->unsignedBigInteger('credit_type_id');
            $table->bigInteger('balance')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'credit_type_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('aero_credits_accounts');
    }
};
