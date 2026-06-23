<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_masterads_ai_analyses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('workspace_id')
                ->constrained('aero_masterads_workspaces')
                ->cascadeOnDelete();
            $table->foreignId('ai_provider_id')
                ->constrained('aero_masterads_ai_providers')
                ->restrictOnDelete();
            $table->enum('target_type', ['campaign', 'adset', 'ad']);
            $table->unsignedBigInteger('target_id');
            $table->enum('status', ['queued', 'running', 'success', 'failed'])
                ->default('queued');
            $table->json('prompt_payload')->nullable();
            $table->json('raw_response')->nullable();
            $table->json('metrics_snapshot')->nullable();
            $table->unsignedBigInteger('tokens_used')->default(0);
            $table->decimal('cost_usd', 12, 6)->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'status']);
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_masterads_ai_analyses');
    }
};
