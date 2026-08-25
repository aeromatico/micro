<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_crm_settings', function (Blueprint $table) {
            $table->boolean('collections_enabled')->default(true);
            $table->unsignedInteger('reminder_interval_days')->default(3);
            $table->text('reminder_message_template')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('aero_crm_settings', function (Blueprint $table) {
            $table->dropColumn(['collections_enabled', 'reminder_interval_days', 'reminder_message_template']);
        });
    }
};
