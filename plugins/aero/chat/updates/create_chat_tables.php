<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_chat_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedInteger('user_id')->index();
            $table->char('token_hash', 64)->unique();
            $table->string('device', 120)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        // Notas internas y delegaciones: viven junto a la conversación pero
        // nunca se envían al cliente.
        Schema::create('aero_chat_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('type', 30);
            $table->text('body')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
            $table->index(['conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_chat_events');
        Schema::dropIfExists('aero_chat_tokens');
    }
};
