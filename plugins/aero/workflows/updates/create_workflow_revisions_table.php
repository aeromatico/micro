<?php

use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Copias de seguridad de un workflow antes de que un agente lo edite. Permiten
 * deshacer un cambio (workflows_revert) aunque el flujo esté publicado y en vivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('aero_workflows_revisions')) {
            return;
        }

        Schema::create('aero_workflows_revisions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('workflow_id')->index();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedInteger('version')->default(1);       // versión que tenía ANTES del cambio
            $table->string('name', 190);
            $table->string('trigger_type', 16);
            $table->json('trigger_config')->nullable();
            $table->mediumText('graph')->nullable();
            $table->string('source', 16)->default('agent');
            $table->text('note')->nullable();                      // el plan acordado que motivó el cambio
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_workflows_revisions');
    }
};
