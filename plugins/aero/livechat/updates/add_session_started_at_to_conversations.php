<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_livechat_conversations', function (Blueprint $table) {
            $table->timestamp('session_started_at')->nullable()->after('last_message_at');
        });
    }

    public function down(): void
    {
        Schema::table('aero_livechat_conversations', function (Blueprint $table) {
            $table->dropColumn('session_started_at');
        });
    }
};
