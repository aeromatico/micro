<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_masterads_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')
                ->constrained('aero_masterads_workspaces')
                ->cascadeOnDelete();
            $table->foreignId('plan_id')
                ->constrained('aero_masterads_plans')
                ->restrictOnDelete();
            $table->enum('status', ['active', 'past_due', 'canceled', 'trialing'])
                ->default('trialing');
            $table->date('period_start');
            $table->date('period_end');
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_masterads_subscriptions');
    }
};
