<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Distingue si `signup_domain` es uno nuevo que pedimos registrar (cobra
 * Settings::getDomainRegistrationPrice()) o uno que el cliente ya tenía y
 * solo nos avisó para que lo apuntemos a la plataforma (gratis, sin
 * registro) — el seguimiento manual del equipo es distinto en cada caso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_sites_tenants', function (Blueprint $table) {
            $table->string('signup_domain_source', 20)->nullable()->after('signup_domain')
                ->comment("'register' (lo pidió nuevo, ya pagado) o 'existing' (ya lo tenía, solo avisó)");
        });
    }

    public function down(): void
    {
        Schema::table('aero_sites_tenants', function (Blueprint $table) {
            $table->dropColumn('signup_domain_source');
        });
    }
};
