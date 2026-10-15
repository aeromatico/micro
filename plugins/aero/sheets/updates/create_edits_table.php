<?php

use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Ediciones directas de celdas hechas por un agente (escribir, agregar filas,
 * crear hoja): guarda el «antes» exacto para poder deshacerlas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('aero_sheets_edits')) {
            return;
        }

        Schema::create('aero_sheets_edits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('backend_user_id')->nullable();
            $table->string('kind', 10);                  // write | append | create
            $table->string('spreadsheet_id');
            $table->string('sheet_title')->nullable();
            $table->longText('ranges')->nullable();      // json: [{range, before, after}]
            $table->string('summary')->nullable();
            $table->text('agreed_plan')->nullable();
            $table->timestamp('undone_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Sin reversa destructiva: es historial de auditoría.
    }
};
