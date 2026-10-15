<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Contratos «de referencia» de cada agente (lo que se muestra como si ya los
 * hubieran tenido). Se suman a los contratos reales de los tenants. La carga
 * inicial les pone un valor aleatorio de 3 a 30 solo si aún no tienen uno.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('aero_workspaces_staff', 'base_contracts')) {
            Schema::table('aero_workspaces_staff', function (Blueprint $table) {
                $table->unsignedInteger('base_contracts')->default(0)->after('sort_order');
            });
        }

        $ids = DB::table('aero_workspaces_staff')->where('is_orchestrator', false)->where('base_contracts', 0)->pluck('id');

        foreach ($ids as $id) {
            DB::table('aero_workspaces_staff')->where('id', $id)->update(['base_contracts' => random_int(3, 30)]);
        }
    }

    public function down(): void
    {
        // Sin reversa destructiva: es aditiva.
    }
};
