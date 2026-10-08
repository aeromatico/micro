<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_livechat_inboxes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('name');
            $table->char('widget_key', 36)->unique();
            $table->text('welcome_message')->nullable();
            $table->string('color', 7)->default('#4f46e5');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('aero_livechat_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->char('visitor_token', 40)->unique();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('aero_livechat_conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('inbox_id')->index();
            $table->unsignedBigInteger('contact_id')->index();
            $table->string('status', 20)->default('open');
            $table->unsignedInteger('assigned_to')->nullable();
            $table->unsignedInteger('agent_unread_count')->default(0);
            $table->unsignedInteger('visitor_unread_count')->default(0);
            $table->string('page_url')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('aero_livechat_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->index();
            $table->string('sender_type', 10);
            $table->unsignedInteger('sender_id')->nullable();
            $table->text('body');
            $table->timestamps();
            $table->index(['conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_livechat_messages');
        Schema::dropIfExists('aero_livechat_conversations');
        Schema::dropIfExists('aero_livechat_contacts');
        Schema::dropIfExists('aero_livechat_inboxes');
    }
};
