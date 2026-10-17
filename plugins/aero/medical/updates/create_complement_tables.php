<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Fase 4: archivos clínicos (cifrados en disco), vacunación, formatos de documentos,
 * lotes de importación y avisos de vacuna por negocio. Los textos clínicos libres van
 * cifrados en el modelo; las fechas y números que alimentan calendarios y hoja, en columnas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aero_medical_imports')) {
            Schema::create('aero_medical_imports', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->unsignedBigInteger('user_id')->nullable();
                $t->string('label')->nullable();
                $t->unsignedInteger('total')->default(0);
                $t->timestamps();

                $t->index(['tenant_id', 'created_at'], 'amimp_tenant');
            });
        }

        if (!Schema::hasTable('aero_medical_files')) {
            Schema::create('aero_medical_files', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->unsignedBigInteger('patient_id')->nullable()->comment('Null mientras una importación espera revisión');
                $t->unsignedBigInteger('consultation_id')->nullable();
                $t->string('kind', 12)->default('other')->comment('lab | imaging | history | other');
                $t->text('title')->nullable()->comment('Cifrado');
                $t->text('original_name')->comment('Cifrado');
                $t->string('mime', 100);
                $t->unsignedBigInteger('size');
                $t->string('storage_name', 64)->unique()->comment('Nombre aleatorio en disco; el contenido va cifrado');
                $t->string('sha256', 64)->comment('Del contenido original');
                $t->date('taken_on')->nullable();
                $t->text('notes')->nullable()->comment('Cifrado');
                $t->unsignedBigInteger('import_id')->nullable();
                $t->string('match_status', 12)->nullable()->comment('pending | suggested | confirmed | discarded');
                $t->unsignedBigInteger('suggested_patient_id')->nullable();
                $t->string('suggestion_reason')->nullable();
                $t->unsignedBigInteger('uploaded_by')->nullable();
                $t->softDeletes();
                $t->timestamps();

                $t->index(['tenant_id', 'patient_id'], 'amf_patient');
                $t->index(['tenant_id', 'import_id'], 'amf_import');
                $t->index(['tenant_id', 'kind'], 'amf_kind');
            });
        }

        Schema::table('aero_medical_lab_results', function (Blueprint $t) {
            if (!Schema::hasColumn('aero_medical_lab_results', 'file_id')) {
                $t->unsignedBigInteger('file_id')->nullable()->comment('Estudio del que salió el resultado');
            }
        });

        if (!Schema::hasTable('aero_medical_vaccines')) {
            Schema::create('aero_medical_vaccines', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->string('name');
                $t->string('species', 32)->nullable()->comment('Solo animal; null = todas');
                $t->text('schedule')->nullable()->comment('JSON [{label, after_days}] por dosis; la 1.ª no lleva after_days');
                $t->unsignedSmallInteger('booster_every_days')->nullable()->comment('Refuerzo periódico tras la última dosis');
                $t->boolean('is_active')->default(true);
                $t->timestamps();

                $t->index(['tenant_id', 'name'], 'amvac_tenant');
            });
        }

        if (!Schema::hasTable('aero_medical_vaccinations')) {
            Schema::create('aero_medical_vaccinations', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->unsignedBigInteger('patient_id');
                $t->unsignedBigInteger('vaccine_id')->nullable();
                $t->string('vaccine_name');
                $t->string('dose_label')->nullable();
                $t->date('applied_on');
                $t->string('lot', 64)->nullable();
                $t->date('next_due_on')->nullable();
                $t->unsignedBigInteger('applied_by')->nullable();
                $t->unsignedBigInteger('consultation_id')->nullable();
                $t->text('notes')->nullable()->comment('Cifrado');
                $t->timestamp('reminder_sent_at')->nullable();
                $t->timestamps();

                $t->index(['tenant_id', 'patient_id'], 'amvn_patient');
                $t->index(['tenant_id', 'next_due_on'], 'amvn_due');
            });
        }

        if (!Schema::hasTable('aero_medical_document_templates')) {
            Schema::create('aero_medical_document_templates', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->string('name');
                $t->string('kind', 16)->default('certificate')->comment('certificate | constancia | report | consent | other');
                $t->longText('body')->comment('Texto con variables {{paciente}}, {{fecha}}…');
                $t->boolean('is_active')->default(true);
                $t->timestamps();

                $t->index(['tenant_id', 'kind'], 'amdt_tenant');
            });
        }

        if (!Schema::hasTable('aero_medical_documents')) {
            Schema::create('aero_medical_documents', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->unsignedBigInteger('patient_id');
                $t->unsignedBigInteger('consultation_id')->nullable();
                $t->unsignedBigInteger('template_id')->nullable();
                $t->string('kind', 16)->default('certificate');
                $t->string('title');
                $t->longText('body')->comment('Cifrado: texto ya con las variables resueltas');
                $t->string('status', 8)->default('draft')->comment('draft | issued');
                $t->unsignedBigInteger('user_id');
                $t->string('professional_name')->nullable();
                $t->timestamp('issued_at')->nullable();
                $t->string('content_hash', 64)->nullable();
                $t->softDeletes();
                $t->timestamps();

                $t->index(['tenant_id', 'patient_id'], 'amdoc_patient');
            });
        }

        Schema::table('aero_medical_settings', function (Blueprint $t) {
            if (!Schema::hasColumn('aero_medical_settings', 'vaccine_reminders')) {
                $t->boolean('vaccine_reminders')->default(false)->comment('Apagado por defecto: no escribir a clientes sin que el negocio lo active');
            }
            if (!Schema::hasColumn('aero_medical_settings', 'reminder_days_before')) {
                $t->unsignedSmallInteger('reminder_days_before')->default(7);
            }
        });
    }

    public function down(): void
    {
        foreach (['documents', 'document_templates', 'vaccinations', 'vaccines', 'files', 'imports'] as $t) {
            Schema::dropIfExists('aero_medical_' . $t);
        }
    }
};
