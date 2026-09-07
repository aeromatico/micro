<?php

use Illuminate\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Costo opcional en créditos por llamada exitosa, para que Aero.Credits (si
 * está instalado) pueda cobrarlo — ver evento 'aero.connector.afterRun' en
 * ConnectorClient::run() y Aero\Credits\Plugin::bootConnectorIntegration().
 * `credit_type_id` no lleva FK dura: referencia opcional a
 * aero_credits_types.id, que puede no existir si Aero.Credits no está
 * instalado.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('aero_connector_connectors', function (Blueprint $table) {
            $table->unsignedInteger('credit_cost')->nullable()->after('is_enabled');
            $table->unsignedBigInteger('credit_type_id')->nullable()->after('credit_cost');
        });
    }

    public function down()
    {
        Schema::table('aero_connector_connectors', function (Blueprint $table) {
            $table->dropColumn(['credit_cost', 'credit_type_id']);
        });
    }
};
