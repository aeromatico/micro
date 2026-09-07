<?php

use Illuminate\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Puerto opcional para cuando el proveedor corre en uno distinto al de la
 * URL base (despliegues propios tipo Ollama/LM Studio, gateways internos).
 * Ver Connector::resolvedBaseUrl().
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('aero_connector_connectors', function (Blueprint $table) {
            $table->unsignedSmallInteger('port')->nullable()->after('base_url');
        });
    }

    public function down()
    {
        Schema::table('aero_connector_connectors', function (Blueprint $table) {
            $table->dropColumn('port');
        });
    }
};
