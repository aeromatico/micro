<?php

use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('aero_shortcuts_items') && !Schema::hasColumn('aero_shortcuts_items', 'new_tab')) {
            Schema::table('aero_shortcuts_items', function (Blueprint $table) {
                $table->boolean('new_tab')->default(true)->after('url');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('aero_shortcuts_items', 'new_tab')) {
            Schema::table('aero_shortcuts_items', function (Blueprint $table) {
                $table->dropColumn('new_tab');
            });
        }
    }
};
