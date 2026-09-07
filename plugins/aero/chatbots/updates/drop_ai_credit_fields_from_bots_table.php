<?php

use Illuminate\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * El costo en créditos por respuesta de IA deja de ser editable por bot (un
 * tenant admin no debería poder fijar su propio costo/color): ahora vive en
 * aero_chatbots_ai_models, un catálogo global que solo administra el
 * superadmin desde "Configuración" > "Modelos de IA".
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('aero_chatbots_bots', function (Blueprint $table) {
            $table->dropColumn(['ai_credit_cost', 'ai_credit_type_id']);
        });
    }

    public function down()
    {
        Schema::table('aero_chatbots_bots', function (Blueprint $table) {
            $table->unsignedInteger('ai_credit_cost')->nullable()->after('ai_system_prompt');
            $table->unsignedBigInteger('ai_credit_type_id')->nullable()->after('ai_credit_cost');
        });
    }
};
