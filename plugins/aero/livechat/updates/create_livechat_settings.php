<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Ajustes del puente a WhatsApp, uno por tenant (tenant_id NULL = plataforma).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_livechat_settings', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->unsignedInteger('tenant_id')->nullable()->unique();
            $table->boolean('whatsapp_enabled')->default(false);
            $table->unsignedInteger('hello_account_id')->nullable();
            $table->string('whatsapp_to', 32)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_livechat_settings');
    }
};
