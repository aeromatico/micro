<?php

use Illuminate\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Reemplaza el switch binario `ai_enabled` por un selector de 3 modos:
 * autoresponder (reglas + mensaje por defecto, como siempre) | ai (fallback
 * de IA) | super_ai (reservado — hoy se comporta igual que `ai`, ver
 * ChatbotEngine::AI_REPLY_MODES).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('aero_chatbots_bots', function (Blueprint $table) {
            $table->string('reply_mode')->default('autoresponder')->after('account_id');
        });

        DB::table('aero_chatbots_bots')->where('ai_enabled', true)->update(['reply_mode' => 'ai']);

        Schema::table('aero_chatbots_bots', function (Blueprint $table) {
            $table->dropColumn('ai_enabled');
        });
    }

    public function down()
    {
        Schema::table('aero_chatbots_bots', function (Blueprint $table) {
            $table->boolean('ai_enabled')->default(false)->after('account_id');
        });

        DB::table('aero_chatbots_bots')->where('reply_mode', '!=', 'autoresponder')->update(['ai_enabled' => true]);

        Schema::table('aero_chatbots_bots', function (Blueprint $table) {
            $table->dropColumn('reply_mode');
        });
    }
};
