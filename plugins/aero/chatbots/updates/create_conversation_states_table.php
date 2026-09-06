<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_chatbots_conversation_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained('aero_chatbots_bots')->cascadeOnDelete();
            $table->unsignedBigInteger('conversation_id')->unique();
            $table->foreign('conversation_id')->references('id')->on('aero_hello_conversations')->cascadeOnDelete();
            $table->timestamp('paused_until')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_chatbots_conversation_states');
    }
};
