<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Fase 5 (solo modo animal): desparasitaciones y visitas de peluquería y spa. Las fotos antes/después
 * son archivos cifrados (aero_medical_files) ligados a la visita; los avisos de desparasitación van
 * apagados por defecto y sin intervalo de fábrica (el negocio decide).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aero_medical_dewormings')) {
            Schema::create('aero_medical_dewormings', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->unsignedBigInteger('patient_id');
                $t->string('product');
                $t->string('kind', 10)->nullable()->comment('internal | external | both');
                $t->string('dose', 100)->nullable();
                $t->date('applied_on');
                $t->date('next_due_on')->nullable();
                $t->string('lot', 64)->nullable();
                $t->unsignedBigInteger('applied_by')->nullable();
                $t->unsignedBigInteger('consultation_id')->nullable();
                $t->text('notes')->nullable()->comment('Cifrado');
                $t->timestamp('reminder_sent_at')->nullable();
                $t->timestamps();

                $t->index(['tenant_id', 'patient_id'], 'amdw_patient');
                $t->index(['tenant_id', 'next_due_on'], 'amdw_due');
            });
        }

        if (!Schema::hasTable('aero_medical_grooming_visits')) {
            Schema::create('aero_medical_grooming_visits', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->unsignedBigInteger('patient_id');
                $t->string('service');
                $t->date('visited_on');
                $t->text('notes')->nullable()->comment('Cifrado');
                $t->date('next_visit_on')->nullable();
                $t->unsignedBigInteger('performed_by')->nullable();
                $t->unsignedBigInteger('booking_id')->nullable()->comment('Reserva de la próxima cita en Aero.Office');
                $t->softDeletes();
                $t->timestamps();

                $t->index(['tenant_id', 'patient_id', 'visited_on'], 'amgv_patient');
                $t->index(['tenant_id', 'visited_on'], 'amgv_date');
            });
        }

        Schema::table('aero_medical_files', function (Blueprint $t) {
            if (!Schema::hasColumn('aero_medical_files', 'grooming_id')) {
                $t->unsignedBigInteger('grooming_id')->nullable()->index()->comment('Visita de peluquería a la que pertenece la foto');
            }
        });

        Schema::table('aero_medical_settings', function (Blueprint $t) {
            if (!Schema::hasColumn('aero_medical_settings', 'deworming_reminders')) {
                $t->boolean('deworming_reminders')->default(false)->comment('Apagado por defecto');
            }
            if (!Schema::hasColumn('aero_medical_settings', 'deworming_interval_days')) {
                $t->unsignedSmallInteger('deworming_interval_days')->nullable()->comment('Intervalo habitual entre desparasitaciones; vacío = no se calcula la próxima');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_medical_grooming_visits');
        Schema::dropIfExists('aero_medical_dewormings');
    }
};
