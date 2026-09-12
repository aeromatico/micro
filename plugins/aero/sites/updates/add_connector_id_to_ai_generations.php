<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use October\Rain\Support\Facades\Schema;

/**
 * Sin FK real a `aero_connector_connectors`: Aero.Connector es una
 * dependencia blanda (igual que en Aero.Chatbots) — si el plugin no está
 * instalado, esta columna simplemente queda siempre en null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_sites_ai_generations', function (Blueprint $table) {
            $table->unsignedBigInteger('connector_id')->nullable()->after('archetype_handle');
        });
    }

    public function down(): void
    {
        Schema::table('aero_sites_ai_generations', function (Blueprint $table) {
            $table->dropColumn('connector_id');
        });
    }
};
