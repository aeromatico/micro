<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_hub_endpoints', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('category');
            $table->string('division');
            $table->string('path');
            $table->string('method', 10);
            $table->string('summary')->nullable();
            $table->text('description')->nullable();
            $table->text('request_schema')->nullable();

            $table->string('pricing_type')->default('fixed');
            $table->unsignedInteger('unit_size')->nullable();
            $table->decimal('unit_cost_usd', 10, 4)->nullable();
            $table->string('count_path')->nullable();
            $table->decimal('reference_cost_usd', 10, 4)->nullable();

            $table->unsignedBigInteger('credit_type_id')->nullable();
            $table->unsignedInteger('credit_cost')->default(0);
            $table->unsignedInteger('overage_credit_cost')->nullable();

            $table->boolean('is_streaming')->default(false);
            $table->boolean('is_async')->default(false);
            $table->boolean('is_active')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index(['path', 'method']);
            $table->index('division');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_hub_endpoints');
    }
};
