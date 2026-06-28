<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_masterads_usage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')
                ->constrained('aero_masterads_subscriptions')
                ->cascadeOnDelete();
            $table->enum('metric', ['analysis', 'sync', 'applied_action']);
            $table->unsignedInteger('qty')->default(1);
            $table->dateTime('recorded_at');
            $table->timestamps();

            $table->index(['subscription_id', 'metric', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_masterads_usage_records');
    }
};
