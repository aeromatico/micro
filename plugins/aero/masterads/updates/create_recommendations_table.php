<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_masterads_recommendations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('ai_analysis_id')
                ->constrained('aero_masterads_ai_analyses')
                ->cascadeOnDelete();
            $table->enum('action_type', [
                'adjust_budget',
                'pause',
                'resume',
                'scale',
                'change_audience',
                'change_creative',
            ]);
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])
                ->default('medium');
            $table->enum('status', ['pending', 'approved', 'rejected', 'applied', 'failed'])
                ->default('pending');
            $table->text('rationale');
            $table->json('payload');
            $table->json('expected_impact')->nullable();
            $table->timestamps();

            $table->index(['ai_analysis_id', 'status']);
            $table->index(['status', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_masterads_recommendations');
    }
};
