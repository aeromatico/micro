<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_hub_endpoints', function (Blueprint $table) {
            // null = usa el margen global de Aero\Hub\Models\Settings; si el
            // superadmin quiere una ganancia distinta para un endpoint puntual,
            // carga los dos acá y pisan el default global solo para esa fila.
            $table->string('margin_type', 10)->nullable()->after('reference_cost_usd');
            $table->decimal('margin_value', 10, 4)->nullable()->after('margin_type');
        });
    }

    public function down(): void
    {
        Schema::table('aero_hub_endpoints', function (Blueprint $table) {
            $table->dropColumn(['margin_type', 'margin_value']);
        });
    }
};
