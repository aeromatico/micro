<?php

use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/** Interruptor general de Finanzas por tenant (encendido por defecto). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('aero_finance_settings') && !Schema::hasColumn('aero_finance_settings', 'enabled')) {
            Schema::table('aero_finance_settings', function (Blueprint $t) {
                $t->boolean('enabled')->default(true)->after('tenant_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('aero_finance_settings', 'enabled')) {
            Schema::table('aero_finance_settings', function (Blueprint $t) {
                $t->dropColumn('enabled');
            });
        }
    }
};
