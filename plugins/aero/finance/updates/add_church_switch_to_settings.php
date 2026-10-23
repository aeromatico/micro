<?php

use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/** Registro automático de los aportes de la iglesia (Aero.Church). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('aero_finance_settings') && !Schema::hasColumn('aero_finance_settings', 'post_church')) {
            Schema::table('aero_finance_settings', function (Blueprint $t) {
                $t->boolean('post_church')->default(true)->after('post_medical');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('aero_finance_settings', 'post_church')) {
            Schema::table('aero_finance_settings', function (Blueprint $t) {
                $t->dropColumn('post_church');
            });
        }
    }
};
