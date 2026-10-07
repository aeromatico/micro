<?php

use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Conversación real de un tenant con un agente que trabaja de verdad (hoy Link).
 * Cada turno del usuario crea un mensaje suyo y uno «pendiente» del agente que un
 * job completa (puede tardar varias llamadas al modelo y a sus herramientas).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('aero_workspaces_messages')) {
            return;
        }

        Schema::create('aero_workspaces_messages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedInteger('staff_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('role', 12);                       // user | assistant
            $table->mediumText('content')->nullable();
            $table->string('status', 12)->default('done');    // pending | done | error
            $table->json('meta')->nullable();                 // herramientas usadas y workflows creados
            $table->string('error', 500)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'staff_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_workspaces_messages');
    }
};
