<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Updates\Migration;

/** El aviso nace activo en la plataforma (tenant_id NULL); los tenants lo activan ellos. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aero_rgpd_configs')) {
            return;
        }
        if (DB::table('aero_rgpd_configs')->whereNull('tenant_id')->exists()) {
            return;
        }

        DB::table('aero_rgpd_configs')->insert([
            'tenant_id' => null, 'is_enabled' => true, 'policy_url' => '/privacy',
            'position' => 'bottom', 'use_analytics' => true, 'use_marketing' => true,
            'keep_log' => true, 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // No se borra nada: la configuración es dato del operador.
    }
};
