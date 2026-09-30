<?php

use Illuminate\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Interruptor "Incluir respuestas automáticas" para los modos IA: si está
 * activo (default, el comportamiento de siempre) se revisan primero las
 * reglas del chatbot simple y la IA solo responde si ninguna matchea.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('aero_chatbots_bots', function (Blueprint $table) {
            $table->boolean('ai_include_rules')->default(true)->after('ai_system_prompt');
        });
    }

    public function down()
    {
        Schema::table('aero_chatbots_bots', function (Blueprint $table) {
            $table->dropColumn('ai_include_rules');
        });
    }
};
