<?php

use Illuminate\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Capacidad de responder con IA como fallback cuando ninguna Rule matchea:
 * el bot elige un Connector de Aero.Connector (tipo IA) y un modelo del
 * catálogo predefinido (ver Aero\Connector\Classes\AiModelCatalog). Ninguna
 * columna lleva FK dura porque Aero.Connector/Aero.Credits son dependencias
 * opcionales (ver Aero\Chatbots\Classes\ChatbotEngine::tryAiReply()).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('aero_chatbots_bots', function (Blueprint $table) {
            $table->unsignedBigInteger('ai_connector_id')->nullable()->after('account_id');
            $table->string('ai_model')->nullable()->after('ai_connector_id');
            $table->text('ai_system_prompt')->nullable()->after('ai_model');
            $table->unsignedInteger('ai_credit_cost')->nullable()->after('ai_system_prompt');
            $table->unsignedBigInteger('ai_credit_type_id')->nullable()->after('ai_credit_cost');

            $table->index('ai_connector_id');
        });
    }

    public function down()
    {
        Schema::table('aero_chatbots_bots', function (Blueprint $table) {
            $table->dropColumn(['ai_connector_id', 'ai_model', 'ai_system_prompt', 'ai_credit_cost', 'ai_credit_type_id']);
        });
    }
};
