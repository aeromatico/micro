<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_livechat_settings', function (Blueprint $table) {
            $table->boolean('livechat_enabled')->default(true)->after('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('aero_livechat_settings', function (Blueprint $table) {
            $table->dropColumn('livechat_enabled');
        });
    }
};
