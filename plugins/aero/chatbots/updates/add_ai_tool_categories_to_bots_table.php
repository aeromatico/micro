<?php

use Illuminate\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Categorías de "AI tools" habilitadas para el modo Súper IA (ver
 * Aero\Chatbots\Classes\AiToolRegistry::forBot). Array jsonable, ej.
 * `["site","shop"]` — vacío/null = ninguna fuente extra habilitada.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('aero_chatbots_bots', function (Blueprint $table) {
            $table->text('ai_tool_categories')->nullable()->after('ai_system_prompt');
        });
    }

    public function down()
    {
        Schema::table('aero_chatbots_bots', function (Blueprint $table) {
            $table->dropColumn('ai_tool_categories');
        });
    }
};
