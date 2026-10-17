<?php

use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/** Registro automático de los cobros de Historia clínica (Aero.Medical). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('aero_finance_settings') && !Schema::hasColumn('aero_finance_settings', 'post_medical')) {
            Schema::table('aero_finance_settings', function (Blueprint $t) {
                $t->boolean('post_medical')->default(true)->after('post_portal');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('aero_finance_settings', 'post_medical')) {
            Schema::table('aero_finance_settings', function (Blueprint $t) {
                $t->dropColumn('post_medical');
            });
        }
    }
};
