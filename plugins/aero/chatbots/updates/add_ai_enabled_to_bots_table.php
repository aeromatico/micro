<?php

use Illuminate\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Switch explícito entre "modo IA" y "modo respondedor simple": antes, la IA
 * se intentaba automáticamente apenas el bot tenía un ai_connector_id
 * configurado. Ahora hace falta prenderla a propósito — permite dejar el
 * connector/modelo elegidos pero pausar la IA sin desarmar la config.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('aero_chatbots_bots', function (Blueprint $table) {
            $table->boolean('ai_enabled')->default(false)->after('account_id');
        });
    }

    public function down()
    {
        Schema::table('aero_chatbots_bots', function (Blueprint $table) {
            $table->dropColumn('ai_enabled');
        });
    }
};
