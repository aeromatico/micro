<?php

use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/** Historial de cambios hechos por agentes de IA en el CRM, con el «antes» para deshacerlos. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('aero_crm_agent_changes')) {
            return;
        }

        Schema::create('aero_crm_agent_changes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('backend_user_id')->nullable();
            $table->string('subject_type', 20);
            $table->unsignedBigInteger('subject_id');
            $table->string('action', 10); // create | update
            $table->longText('before')->nullable();
            $table->string('summary')->nullable();
            $table->text('agreed_plan')->nullable();
            $table->timestamp('undone_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'subject_type', 'subject_id'], 'aero_crm_chg_subject');
        });
    }

    public function down(): void
    {
        // Sin reversa destructiva: es historial de auditoría.
    }
};
