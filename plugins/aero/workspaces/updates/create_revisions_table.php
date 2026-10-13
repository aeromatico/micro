<?php

use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Copia «antes» de cada cambio que un agente hace en el sitio o la tienda del
 * cliente (página, SEO, contacto, producto, colección), para poder deshacerlo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('aero_workspaces_revisions')) {
            return;
        }

        Schema::create('aero_workspaces_revisions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('subject_type', 20);
            $table->unsignedBigInteger('subject_id');
            $table->string('action', 10); // create | update
            $table->longText('before')->nullable();
            $table->string('summary', 255)->nullable();
            $table->text('agreed_plan')->nullable();
            $table->timestamp('undone_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'subject_type', 'subject_id'], 'aero_ws_rev_subject');
        });
    }

    public function down(): void
    {
        // Sin reversa destructiva: es historial de auditoría.
    }
};
