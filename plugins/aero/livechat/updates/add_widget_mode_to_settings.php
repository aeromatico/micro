<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_livechat_settings', function (Blueprint $table) {
            $table->string('widget_mode', 16)->default('livechat')->after('livechat_enabled');
            $table->string('widget_whatsapp', 32)->nullable()->after('widget_mode');
            $table->mediumText('custom_code')->nullable()->after('widget_whatsapp');
        });
    }

    public function down(): void
    {
        Schema::table('aero_livechat_settings', function (Blueprint $table) {
            $table->dropColumn(['widget_mode', 'widget_whatsapp', 'custom_code']);
        });
    }
};
