<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Cada tenant puede apagar módulos (disabled_plugins) y elige los suyos en
 * una bienvenida la primera vez (modules_onboarded_at). Los tenants que ya
 * existían se marcan como «ya pasaron la bienvenida» para no interrumpirlos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('aero_sites_tenants', 'disabled_plugins')) {
            Schema::table('aero_sites_tenants', function (Blueprint $table) {
                $table->json('disabled_plugins')->nullable();
                $table->timestamp('modules_onboarded_at')->nullable();
            });
        }

        DB::table('aero_sites_tenants')->whereNull('modules_onboarded_at')->update(['modules_onboarded_at' => now()]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('aero_sites_tenants', 'disabled_plugins')) {
            Schema::table('aero_sites_tenants', function (Blueprint $table) {
                $table->dropColumn(['disabled_plugins', 'modules_onboarded_at']);
            });
        }
    }
};
