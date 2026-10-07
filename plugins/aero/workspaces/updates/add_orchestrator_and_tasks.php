<?php

use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Orquestador (el agente especial que siempre está en el equipo de todo tenant
 * y recibe cada encargo) y la tabla de encargos de la Oficina.
 *
 * Los encargos guardan el plan (quién hace qué, cuánto dura, cuántos puntos
 * cuesta) y su estado. La ejecución es SIMULADA mientras no exista un motor de
 * agentes: el estado avanza por el reloj (`finished_at`), no por un proceso.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('aero_workspaces_staff', 'is_orchestrator')) {
            Schema::table('aero_workspaces_staff', function (Blueprint $table) {
                $table->boolean('is_orchestrator')->default(false)->after('is_active');
            });
        }

        if (!Schema::hasTable('aero_workspaces_tasks')) {
            Schema::create('aero_workspaces_tasks', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('orchestrator_id')->nullable();   // staff que coordinó
                $table->text('brief');
                $table->string('status', 16)->default('running')->index();   // running | done | cancelled
                $table->unsignedInteger('estimated_points')->default(0);
                $table->unsignedInteger('charged_points')->default(0);
                $table->unsignedBigInteger('credit_transaction_id')->nullable();
                $table->json('plan')->nullable();                            // pasos: staff, etiqueta, duración, puntos
                $table->string('source', 16)->default('office');             // office | mcp
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();                // cuándo termina la simulación
                $table->timestamps();

                $table->index(['tenant_id', 'status']);
            });
        }

        // Sin orquestador definido, la coordinadora del catálogo lo es.
        $has = \Illuminate\Support\Facades\DB::table('aero_workspaces_staff')->where('is_orchestrator', true)->exists();

        if (!$has) {
            $id = \Illuminate\Support\Facades\DB::table('aero_workspaces_staff')
                ->where(fn ($q) => $q->where('slug', 'katy')->orWhere('role', 'like', 'Coordinador%'))
                ->orderBy('id')->value('id');

            if ($id) {
                \Illuminate\Support\Facades\DB::table('aero_workspaces_staff')->where('id', $id)->update(['is_orchestrator' => true, 'is_active' => true]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_workspaces_tasks');

        if (Schema::hasColumn('aero_workspaces_staff', 'is_orchestrator')) {
            Schema::table('aero_workspaces_staff', fn (Blueprint $table) => $table->dropColumn('is_orchestrator'));
        }
    }
};
