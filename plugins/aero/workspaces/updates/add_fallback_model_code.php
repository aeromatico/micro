<?php

use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/** Modelo de respaldo por agente: código del catálogo de Ajustes que toma el turno si el principal falla. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('aero_workspaces_staff', 'fallback_model_code')) {
            Schema::table('aero_workspaces_staff', function (Blueprint $table) {
                $table->string('fallback_model_code', 40)->nullable()->after('model_code');
            });
        }
    }

    public function down(): void
    {
        // Sin reversa destructiva: la columna es aditiva.
    }
};
