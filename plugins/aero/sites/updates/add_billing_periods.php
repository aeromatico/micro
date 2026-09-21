<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Precio por periodo. Plan.price sigue siendo el precio MENSUAL; se agregan
 * price_annual (null = no se ofrece anual) y trial_days (0 = sin prueba).
 * El tenant guarda el periodo contratado y cuándo vence.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('aero_sites_plans', function (Blueprint $table) {
            $table->decimal('price_annual', 10, 2)->nullable()->after('price');
            $table->unsignedInteger('trial_days')->default(0)->after('price_annual');
        });

        Schema::table('aero_sites_tenants', function (Blueprint $table) {
            $table->string('billing_period', 16)->nullable()->after('plan_price');
            $table->timestamp('plan_expires_at')->nullable()->after('billing_period')->index();
        });
    }

    public function down()
    {
        Schema::table('aero_sites_tenants', function (Blueprint $table) {
            $table->dropColumn(['billing_period', 'plan_expires_at']);
        });

        Schema::table('aero_sites_plans', function (Blueprint $table) {
            $table->dropColumn(['price_annual', 'trial_days']);
        });
    }
};
