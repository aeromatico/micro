<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/** Interruptor de los avisos a la administración (vencimientos, consultas, documentos). Por defecto activo. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('aero_realestate_settings', 'notify_admins')) {
            Schema::table('aero_realestate_settings', function (Blueprint $t) {
                $t->boolean('notify_admins')->default(true);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('aero_realestate_settings', 'notify_admins')) {
            Schema::table('aero_realestate_settings', function (Blueprint $t) {
                $t->dropColumn('notify_admins');
            });
        }
    }
};
