<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_masterads_ad_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')
                ->constrained('aero_masterads_campaigns')
                ->cascadeOnDelete();
            $table->string('meta_id', 64)->unique();
            $table->string('name', 255);
            $table->enum('status', ['ACTIVE', 'PAUSED', 'ARCHIVED', 'DELETED'])->default('PAUSED');
            $table->json('targeting')->nullable();
            $table->decimal('daily_budget', 12, 4)->nullable();
            $table->string('optimization_goal', 64)->nullable();
            $table->string('bid_strategy', 64)->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_masterads_ad_sets');
    }
};
