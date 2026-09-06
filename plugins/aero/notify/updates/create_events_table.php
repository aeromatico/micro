<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Catálogo global de eventos notificables. Solo el superadmin lo administra;
 * los tenants consumen los códigos que aquí se declaran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_notify_events', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('source_plugin', 50)->nullable();
            $table->string('category', 50)->default('general');
            $table->string('name', 150);
            $table->text('description')->nullable();

            // Contrato de variables de plantilla. No es documentación: lo valida
            // Notify::fire() y alimenta el editor y el preview.
            $table->json('variables_schema')->nullable();
            $table->json('sample_context')->nullable();

            // Usados al sembrar las reglas por defecto de un evento.
            $table->json('default_channels')->nullable();
            $table->json('default_audiences')->nullable();

            // 1 crítico … 9 informativo. Gobierna la cola y las quiet hours.
            $table->unsignedTinyInteger('priority')->default(5);

            // Los transaccionales ignoran el opt-out global "*+*".
            $table->boolean('is_transactional')->default(true);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);

            $table->timestamps();

            $table->index(['category', 'is_active']);
            $table->index('source_plugin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_notify_events');
    }
};
