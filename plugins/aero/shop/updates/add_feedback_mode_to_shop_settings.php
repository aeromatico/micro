<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/** Qué hace la tienda con las opiniones: off | internal | public | moderated. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_shop_settings', function (Blueprint $table) {
            $table->string('feedback_mode', 12)->default('off')->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('aero_shop_settings', function (Blueprint $table) {
            $table->dropColumn('feedback_mode');
        });
    }
};
