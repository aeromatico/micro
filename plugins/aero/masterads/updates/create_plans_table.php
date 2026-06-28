<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_masterads_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 120);
            $table->decimal('monthly_price', 10, 2)->default(0);
            $table->unsignedInteger('max_meta_accounts')->default(1);
            $table->unsignedInteger('max_analyses_month')->default(10);
            $table->boolean('auto_apply_allowed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_masterads_plans');
    }
};
