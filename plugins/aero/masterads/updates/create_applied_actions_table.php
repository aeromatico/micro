<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_masterads_applied_actions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('recommendation_id')
                ->constrained('aero_masterads_recommendations')
                ->cascadeOnDelete();
            $table->foreignId('applied_by')
                ->constrained('backend_users')
                ->restrictOnDelete();
            $table->boolean('success');
            $table->json('before_state');
            $table->json('after_state')->nullable();
            $table->json('meta_response')->nullable();
            $table->timestamps();

            // MySQL does not support true partial indexes; enforcing UNIQUE
            // on (recommendation_id, success) guarantees at most one row per
            // (recommendation, success_value) pair.
            $table->unique(['recommendation_id', 'success'], 'applied_actions_rec_success_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_masterads_applied_actions');
    }
};
