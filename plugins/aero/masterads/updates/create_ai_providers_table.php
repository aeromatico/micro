<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_masterads_ai_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')
                ->nullable()
                ->constrained('aero_masterads_workspaces')
                ->cascadeOnDelete();
            $table->string('name', 120);
            $table->enum('driver', ['openrouter', 'openai', 'anthropic', 'custom'])
                ->default('openrouter');
            $table->string('model', 255);
            $table->text('api_key')->comment('Encrypted via Crypt::encrypt mutator');
            $table->boolean('is_default')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_masterads_ai_providers');
    }
};
