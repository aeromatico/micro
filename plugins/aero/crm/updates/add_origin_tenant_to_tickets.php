<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use October\Rain\Support\Facades\Schema;

/**
 * Soporte de plataforma para tenants: el ticket vive en la mesa de ayuda de la
 * plataforma (tenant_id NULL) pero recuerda qué tenant lo abrió. Es distinto
 * del CRM del tenant, donde tenant_id apunta al propio tenant.
 *
 * Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('aero_crm_tickets', 'origin_tenant_id')) {
            Schema::table('aero_crm_tickets', function (Blueprint $table) {
                $table->unsignedBigInteger('origin_tenant_id')->nullable()->after('tenant_id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('aero_crm_tickets', 'origin_tenant_id')) {
            Schema::table('aero_crm_tickets', function (Blueprint $table) {
                $table->dropColumn('origin_tenant_id');
            });
        }
    }
};
