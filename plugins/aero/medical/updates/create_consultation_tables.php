<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Fase 2: consultas/controles, signos vitales, adendas, plantillas de nota,
 * perfil del profesional (firma y sello) y enlace reserva→paciente.
 * El texto clínico de la nota va cifrado (cast `encrypted:array` en el modelo);
 * los signos vitales son números en columnas porque alimentan la hoja de seguimiento.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aero_medical_professionals')) {
            Schema::create('aero_medical_professionals', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->unsignedBigInteger('user_id');
                $t->string('display_name');
                $t->string('title', 32)->nullable()->comment('Dr., Dra., MV…');
                $t->string('license', 64)->nullable()->comment('Matrícula profesional');
                $t->string('specialty', 128)->nullable();
                $t->timestamps();

                $t->unique(['tenant_id', 'user_id']);
            });
        }

        if (!Schema::hasTable('aero_medical_note_templates')) {
            Schema::create('aero_medical_note_templates', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->string('kind', 16)->comment('consultation | control');
                $t->string('name');
                $t->text('sections')->nullable()->comment('JSON: [{key,label,type text|vitals,rows}]');
                $t->boolean('is_active')->default(true);
                $t->timestamps();

                $t->index(['tenant_id', 'kind']);
            });
        }

        if (!Schema::hasTable('aero_medical_consultations')) {
            Schema::create('aero_medical_consultations', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->unsignedBigInteger('patient_id');
                $t->string('kind', 16)->default('consultation');
                $t->unsignedBigInteger('template_id')->nullable();
                $t->string('template_name')->nullable();
                $t->text('sections')->nullable()->comment('Foto de las secciones de la plantilla al abrir la nota');
                $t->longText('content')->nullable()->comment('JSON cifrado: sección → texto');
                $t->string('status', 10)->default('draft')->comment('draft | signed');
                $t->unsignedBigInteger('user_id')->comment('Autor (usuario del panel)');
                $t->unsignedBigInteger('worker_id')->nullable()->comment('Profesional de Office, si hay');
                $t->string('professional_name')->nullable()->comment('Foto del nombre al abrir');
                $t->unsignedBigInteger('booking_id')->nullable();
                $t->string('service_name')->nullable();
                $t->timestamp('consulted_at')->nullable();
                $t->timestamp('last_autosave_at')->nullable();
                $t->timestamp('signed_at')->nullable();
                $t->unsignedBigInteger('signed_by')->nullable();
                $t->string('content_hash', 64)->nullable();
                $t->softDeletes();
                $t->timestamps();

                $t->index(['tenant_id', 'patient_id', 'consulted_at'], 'amc_tenant_patient_date');
                $t->index(['tenant_id', 'booking_id'], 'amc_tenant_booking');
                $t->index(['tenant_id', 'user_id', 'status'], 'amc_tenant_author_status');
            });
        }

        if (!Schema::hasTable('aero_medical_vitals')) {
            Schema::create('aero_medical_vitals', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->unsignedBigInteger('patient_id');
                $t->unsignedBigInteger('consultation_id')->nullable()->unique();
                $t->timestamp('measured_at')->nullable();
                $t->unsignedSmallInteger('systolic')->nullable();
                $t->unsignedSmallInteger('diastolic')->nullable();
                $t->unsignedSmallInteger('heart_rate')->nullable();
                $t->unsignedSmallInteger('resp_rate')->nullable();
                $t->decimal('temperature_c', 4, 1)->nullable();
                $t->unsignedSmallInteger('spo2')->nullable();
                $t->decimal('weight_kg', 7, 2)->nullable();
                $t->decimal('height_cm', 5, 1)->nullable();
                $t->decimal('bmi', 4, 1)->nullable();
                $t->string('mucous', 24)->nullable();
                $t->decimal('crt_seconds', 3, 1)->nullable();
                $t->unsignedTinyInteger('body_condition')->nullable();
                $t->timestamps();

                $t->index(['tenant_id', 'patient_id', 'measured_at'], 'amv_tenant_patient_date');
            });
        }

        if (!Schema::hasTable('aero_medical_addenda')) {
            Schema::create('aero_medical_addenda', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->unsignedBigInteger('consultation_id');
                $t->unsignedBigInteger('user_id')->nullable();
                $t->string('user_name')->nullable();
                $t->text('body')->comment('Cifrado en el modelo');
                $t->timestamp('created_at')->nullable();

                $t->index(['tenant_id', 'consultation_id']);
            });
        }

        if (!Schema::hasTable('aero_medical_booking_patients')) {
            Schema::create('aero_medical_booking_patients', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->unsignedBigInteger('booking_id');
                $t->unsignedBigInteger('patient_id');
                $t->timestamps();

                $t->unique(['tenant_id', 'booking_id']);
            });
        }

        Schema::table('aero_medical_settings', function (Blueprint $t) {
            if (!Schema::hasColumn('aero_medical_settings', 'consultation_template_id')) {
                $t->unsignedBigInteger('consultation_template_id')->nullable();
            }
            if (!Schema::hasColumn('aero_medical_settings', 'control_template_id')) {
                $t->unsignedBigInteger('control_template_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_medical_booking_patients');
        Schema::dropIfExists('aero_medical_addenda');
        Schema::dropIfExists('aero_medical_vitals');
        Schema::dropIfExists('aero_medical_consultations');
        Schema::dropIfExists('aero_medical_note_templates');
        Schema::dropIfExists('aero_medical_professionals');
    }
};
