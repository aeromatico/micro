<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_crm_quick_replies', function (Blueprint $table) {
            $table->string('kind', 30)->default('text')->after('area');
            $table->json('config')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('aero_crm_quick_replies', function (Blueprint $table) {
            $table->dropColumn(['kind', 'config']);
        });
    }
};
