<?php

use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/** Modo de acceso por gimnasio: el socio muestra su QR (member_qr) o escanea el del gimnasio (gym_qr). */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aero_gym_settings')) {
            return;
        }
        Schema::table('aero_gym_settings', function (Blueprint $t) {
            if (!Schema::hasColumn('aero_gym_settings', 'access_mode')) {
                $t->string('access_mode', 16)->default('member_qr')->after('enabled');
            }
            if (!Schema::hasColumn('aero_gym_settings', 'qr_rotation_seconds')) {
                $t->unsignedSmallInteger('qr_rotation_seconds')->default(30)->after('access_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('aero_gym_settings', function (Blueprint $t) {
            foreach (['access_mode', 'qr_rotation_seconds'] as $c) {
                if (Schema::hasColumn('aero_gym_settings', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
    }
};
