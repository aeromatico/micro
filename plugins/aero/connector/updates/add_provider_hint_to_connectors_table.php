<?php

use Illuminate\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Override manual opcional para cuando Connector::resolveType() no puede
 * adivinar el tipo por el host de `base_url` (ver Connector::beforeValidate()).
 * null = auto (default); 'ai'|'anthropic'|'http' = fuerza el tipo.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('aero_connector_connectors', function (Blueprint $table) {
            $table->string('provider_hint')->nullable()->after('base_url');
        });
    }

    public function down()
    {
        Schema::table('aero_connector_connectors', function (Blueprint $table) {
            $table->dropColumn('provider_hint');
        });
    }
};
