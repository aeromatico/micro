<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_livechat_messages', function (Blueprint $table) {
            $table->string('attachment_path')->nullable()->after('telegram_message_id');
            $table->string('attachment_name')->nullable()->after('attachment_path');
            $table->string('attachment_mime')->nullable()->after('attachment_name');
            $table->unsignedInteger('attachment_size')->nullable()->after('attachment_mime');
            $table->char('attachment_token', 40)->nullable()->unique()->after('attachment_size');
        });
    }

    public function down(): void
    {
        Schema::table('aero_livechat_messages', function (Blueprint $table) {
            $table->dropColumn(['attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size', 'attachment_token']);
        });
    }
};
