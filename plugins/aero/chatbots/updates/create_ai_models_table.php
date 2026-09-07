<?php

use Illuminate\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Catálogo de modelos de IA predefinidos por el superadmin (menú
 * "Configuración" > "Modelos de IA"), uno por cada (connector, model_id):
 * qué modelos de qué Connector están habilitados para que los bots elijan,
 * y cuánto cobrar en créditos por cada respuesta con ese modelo. Sin FK dura
 * a aero_connector_connectors porque Aero.Connector es dependencia opcional.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('aero_chatbots_ai_models', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connector_id');
            $table->string('model_id');
            $table->string('label');
            $table->unsignedInteger('credit_cost')->nullable();
            $table->unsignedBigInteger('credit_type_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('connector_id');
            $table->unique(['connector_id', 'model_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('aero_chatbots_ai_models');
    }
};
