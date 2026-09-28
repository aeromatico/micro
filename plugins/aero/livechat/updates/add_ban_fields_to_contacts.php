<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_livechat_contacts', function (Blueprint $table) {
            $table->boolean('is_banned')->default(false)->after('last_seen_at');
            $table->timestamp('banned_until')->nullable()->after('is_banned');
        });
    }

    public function down(): void
    {
        Schema::table('aero_livechat_contacts', function (Blueprint $table) {
            $table->dropColumn(['is_banned', 'banned_until']);
        });
    }
};
