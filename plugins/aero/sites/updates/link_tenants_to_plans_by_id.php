<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Tenant.plan (string con el código) → Tenant.plan_id (FK a aero_sites_plans).
 * Así renombrar el código de un plan no deja huérfanos a sus tenants. El
 * código pasa a ser solo el slug público de /comprar/:plan.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('aero_sites_tenants', function (Blueprint $table) {
            $table->unsignedBigInteger('plan_id')->nullable()->after('niche_type')->index();
        });

        DB::statement('UPDATE aero_sites_tenants t JOIN aero_sites_plans p ON p.code = t.plan SET t.plan_id = p.id');

        $orphans = DB::table('aero_sites_tenants')->whereNotNull('plan')->where('plan', '!=', '')->whereNull('plan_id')->count();
        if ($orphans) {
            throw new \RuntimeException("{$orphans} tenant(s) tienen un plan cuyo código no existe en aero_sites_plans; corrígelos antes de migrar.");
        }

        Schema::table('aero_sites_tenants', function (Blueprint $table) {
            $table->dropColumn('plan');
        });
    }

    public function down()
    {
        Schema::table('aero_sites_tenants', function (Blueprint $table) {
            $table->string('plan')->nullable()->after('niche_type');
        });

        DB::statement('UPDATE aero_sites_tenants t JOIN aero_sites_plans p ON p.id = t.plan_id SET t.plan = p.code');

        Schema::table('aero_sites_tenants', function (Blueprint $table) {
            $table->dropColumn('plan_id');
        });
    }
};
