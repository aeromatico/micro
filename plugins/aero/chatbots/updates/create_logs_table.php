<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_chatbots_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained('aero_chatbots_bots')->cascadeOnDelete();
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->foreign('conversation_id')->references('id')->on('aero_hello_conversations')->nullOnDelete();
            $table->unsignedBigInteger('inbound_message_id')->nullable();
            $table->foreign('inbound_message_id')->references('id')->on('aero_hello_messages')->nullOnDelete();
            $table->unsignedBigInteger('outbound_message_id')->nullable();
            $table->foreign('outbound_message_id')->references('id')->on('aero_hello_messages')->nullOnDelete();
            $table->foreignId('rule_id')->nullable()->constrained('aero_chatbots_rules')->nullOnDelete();
            $table->timestamp('matched_at')->nullable();
            $table->timestamps();

            $table->index('bot_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_chatbots_logs');
    }
};
