<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_chatbots_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained('aero_chatbots_bots')->cascadeOnDelete();
            $table->text('trigger_keywords');
            $table->text('response_text');
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['bot_id', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_chatbots_rules');
    }
};
