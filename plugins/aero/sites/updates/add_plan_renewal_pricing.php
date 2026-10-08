<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Precio de renovación, separado del precio de alta. Vacío = igual al precio
 * de alta (price / price_annual). Todavía sin efecto: el cobro recurrente que
 * los use no existe aún (ver plugins/aero/sites/classes/PlanCredits.php).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('aero_sites_plans', function (Blueprint $table) {
            $table->decimal('price_renewal', 10, 2)->nullable()->after('price');
            $table->decimal('price_renewal_annual', 10, 2)->nullable()->after('price_annual');
        });
    }

    public function down()
    {
        Schema::table('aero_sites_plans', function (Blueprint $table) {
            $table->dropColumn(['price_renewal', 'price_renewal_annual']);
        });
    }
};
