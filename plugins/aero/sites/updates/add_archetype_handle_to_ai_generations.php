<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use October\Rain\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_sites_ai_generations', function (Blueprint $table) {
            $table->string('archetype_handle', 100)->nullable()->after('step');
        });
    }

    public function down(): void
    {
        Schema::table('aero_sites_ai_generations', function (Blueprint $table) {
            $table->dropColumn('archetype_handle');
        });
    }
};
