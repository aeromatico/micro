<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use October\Rain\Support\Facades\Schema;

/**
 * Soporte de plataforma: el ticket queda vinculado al ADMIN del tenant (usuario
 * de backend) que lo abrió o a quien el equipo le escribe en nombre del tenant.
 *
 * Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('aero_crm_tickets', 'requester_user_id')) {
            Schema::table('aero_crm_tickets', function (Blueprint $table) {
                $table->unsignedBigInteger('requester_user_id')->nullable()->after('origin_tenant_id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('aero_crm_tickets', 'requester_user_id')) {
            Schema::table('aero_crm_tickets', function (Blueprint $table) {
                $table->dropColumn('requester_user_id');
            });
        }
    }
};
