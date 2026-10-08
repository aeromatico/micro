<?php

use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/** Registro automático de los cobros del portal (solo aplica al libro del tenant master). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('aero_finance_settings') && !Schema::hasColumn('aero_finance_settings', 'post_portal')) {
            Schema::table('aero_finance_settings', function (Blueprint $t) {
                $t->boolean('post_portal')->default(true)->after('post_gym');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('aero_finance_settings', 'post_portal')) {
            Schema::table('aero_finance_settings', function (Blueprint $t) {
                $t->dropColumn('post_portal');
            });
        }
    }
};
