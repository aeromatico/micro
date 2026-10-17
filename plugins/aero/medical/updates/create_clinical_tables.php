<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Fase 3. Catálogos GLOBALES (cie10, liname: sin tenant_id, se cargan con
 * `medical:import-*`, nunca desde aquí) y datos por negocio: diagnósticos,
 * receta, órdenes, cuestionarios, laboratorio manual y rangos.
 * Todo texto clínico libre va cifrado en el modelo; números que alimentan la
 * hoja de seguimiento (laboratorio) van en columnas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aero_medical_cie10')) {
            Schema::create('aero_medical_cie10', function (Blueprint $t) {
                $t->id();
                $t->string('code', 8)->unique();
                $t->unsignedTinyInteger('level')->comment('3 = categoría, 4 = subcategoría');
                $t->string('parent', 8)->nullable()->index();
                $t->unsignedTinyInteger('chapter')->nullable();
                $t->string('title', 500);
            });
        }

        if (!Schema::hasTable('aero_medical_liname')) {
            Schema::create('aero_medical_liname', function (Blueprint $t) {
                $t->id();
                $t->string('code', 16)->nullable()->index();
                $t->string('name');
                $t->string('form')->nullable();
                $t->string('concentration')->nullable();
                $t->string('atc', 16)->nullable();
                $t->boolean('restricted')->default(false);
            });
        }

        if (!Schema::hasTable('aero_medical_diagnosis_catalog')) {
            Schema::create('aero_medical_diagnosis_catalog', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->string('source', 8)->comment('cie10 | custom');
                $t->string('code', 16)->nullable();
                $t->string('label', 500);
                $t->string('entry_key', 64)->comment('código o md5 del texto: evita duplicados');
                $t->boolean('pinned')->default(false)->comment('Frecuente fijado por el negocio');
                $t->unsignedInteger('uses')->default(0)->comment('Veces usado en notas firmadas');
                $t->timestamp('last_used_at')->nullable();
                $t->timestamps();

                $t->unique(['tenant_id', 'source', 'entry_key'], 'amdc_unique_entry');
                $t->index(['tenant_id', 'pinned', 'uses'], 'amdc_tenant_rank');
            });
        }

        if (!Schema::hasTable('aero_medical_diagnoses')) {
            Schema::create('aero_medical_diagnoses', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->unsignedBigInteger('patient_id');
                $t->unsignedBigInteger('consultation_id');
                $t->string('source', 8)->comment('cie10 | custom | free');
                $t->string('code', 16)->nullable();
                $t->string('label', 500);
                $t->boolean('is_primary')->default(false);
                $t->string('certainty', 12)->default('presumptive')->comment('presumptive | confirmed');
                $t->unsignedSmallInteger('position')->default(0);
                $t->timestamps();

                $t->index(['tenant_id', 'consultation_id'], 'amdx_note');
                $t->index(['tenant_id', 'patient_id'], 'amdx_patient');
            });
        }

        if (!Schema::hasTable('aero_medical_medications')) {
            Schema::create('aero_medical_medications', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->string('name');
                $t->string('form')->nullable();
                $t->string('concentration')->nullable();
                $t->string('notes')->nullable();
                $t->boolean('is_active')->default(true);
                $t->timestamps();

                $t->index(['tenant_id', 'name'], 'ammed_tenant_name');
            });
        }

        if (!Schema::hasTable('aero_medical_prescriptions')) {
            Schema::create('aero_medical_prescriptions', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->unsignedBigInteger('patient_id');
                $t->unsignedBigInteger('consultation_id')->unique();
                $t->text('notes')->nullable()->comment('Cifrado en el modelo');
                $t->timestamps();

                $t->index(['tenant_id', 'patient_id'], 'amrx_patient');
            });
        }

        if (!Schema::hasTable('aero_medical_prescription_items')) {
            Schema::create('aero_medical_prescription_items', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->unsignedBigInteger('prescription_id');
                $t->unsignedSmallInteger('position')->default(0);
                $t->string('source', 8)->default('free')->comment('liname | custom | free');
                $t->text('medication')->comment('Cifrado');
                $t->text('dose')->nullable();
                $t->text('route')->nullable();
                $t->text('frequency')->nullable();
                $t->text('duration')->nullable();
                $t->text('quantity')->nullable();
                $t->text('instructions')->nullable();
                $t->timestamps();

                $t->index(['tenant_id', 'prescription_id'], 'amrxi_rx');
            });
        }

        if (!Schema::hasTable('aero_medical_exam_catalog')) {
            Schema::create('aero_medical_exam_catalog', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->string('name');
                $t->string('category', 12)->default('lab')->comment('lab | imaging | other');
                $t->unsignedSmallInteger('sort')->default(0);
                $t->boolean('is_active')->default(true);
                $t->timestamps();

                $t->index(['tenant_id', 'category', 'sort'], 'amex_tenant_cat');
            });
        }

        if (!Schema::hasTable('aero_medical_orders')) {
            Schema::create('aero_medical_orders', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->unsignedBigInteger('patient_id');
                $t->unsignedBigInteger('consultation_id')->unique();
                $t->text('notes')->nullable()->comment('Cifrado en el modelo');
                $t->timestamps();

                $t->index(['tenant_id', 'patient_id'], 'amord_patient');
            });
        }

        if (!Schema::hasTable('aero_medical_order_items')) {
            Schema::create('aero_medical_order_items', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->unsignedBigInteger('order_id');
                $t->unsignedSmallInteger('position')->default(0);
                $t->string('category', 12)->default('lab');
                $t->text('exam_name')->comment('Cifrado');
                $t->text('instructions')->nullable();
                $t->timestamps();

                $t->index(['tenant_id', 'order_id'], 'amordi_order');
            });
        }

        if (!Schema::hasTable('aero_medical_questionnaires')) {
            Schema::create('aero_medical_questionnaires', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->string('name');
                $t->text('description')->nullable();
                $t->longText('questions')->nullable()->comment('JSON [{key,text,options:[{label,score}]}]');
                $t->text('ranges')->nullable()->comment('JSON [{min,max,label}]');
                $t->boolean('is_active')->default(true);
                $t->timestamps();

                $t->index(['tenant_id', 'is_active'], 'amq_tenant');
            });
        }

        if (!Schema::hasTable('aero_medical_questionnaire_answers')) {
            Schema::create('aero_medical_questionnaire_answers', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->unsignedBigInteger('patient_id');
                $t->unsignedBigInteger('consultation_id')->nullable();
                $t->unsignedBigInteger('questionnaire_id')->nullable();
                $t->string('questionnaire_name');
                $t->longText('definition')->nullable()->comment('Foto de la definición al responder');
                $t->longText('answers')->nullable()->comment('Cifrado: key => índice de opción');
                $t->decimal('score', 10, 2)->nullable();
                $t->string('interpretation')->nullable();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->timestamp('answered_at')->nullable();
                $t->timestamps();

                $t->index(['tenant_id', 'patient_id', 'answered_at'], 'amqa_patient');
                $t->index(['tenant_id', 'consultation_id'], 'amqa_note');
            });
        }

        if (!Schema::hasTable('aero_medical_analytes')) {
            Schema::create('aero_medical_analytes', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->string('name');
                $t->string('unit', 32)->nullable();
                $t->unsignedSmallInteger('sort')->default(0);
                $t->boolean('is_active')->default(true);
                $t->timestamps();

                $t->index(['tenant_id', 'sort'], 'amana_tenant');
            });
        }

        if (!Schema::hasTable('aero_medical_lab_results')) {
            Schema::create('aero_medical_lab_results', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->unsignedBigInteger('patient_id');
                $t->unsignedBigInteger('consultation_id')->nullable();
                $t->unsignedBigInteger('analyte_id')->nullable();
                $t->string('analyte_name');
                $t->string('unit', 32)->nullable();
                $t->decimal('value', 14, 3)->nullable();
                $t->date('measured_on');
                $t->text('note')->nullable()->comment('Cifrado');
                $t->unsignedBigInteger('user_id')->nullable();
                $t->timestamps();

                $t->index(['tenant_id', 'patient_id', 'measured_on'], 'amlab_patient_date');
            });
        }

        if (!Schema::hasTable('aero_medical_ranges')) {
            Schema::create('aero_medical_ranges', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->string('mode', 8)->comment('human | animal');
                $t->string('species', 32)->nullable()->comment('Solo animal; null = todas');
                $t->string('metric', 48)->comment('Campo de signo vital o analyte:<id>');
                $t->decimal('low', 14, 3)->nullable();
                $t->decimal('high', 14, 3)->nullable();
                $t->timestamps();

                $t->index(['tenant_id', 'mode', 'metric'], 'amrng_lookup');
            });
        }
    }

    public function down(): void
    {
        foreach (['ranges', 'lab_results', 'analytes', 'questionnaire_answers', 'questionnaires', 'order_items', 'orders', 'exam_catalog',
                  'prescription_items', 'prescriptions', 'medications', 'diagnoses', 'diagnosis_catalog', 'liname', 'cie10'] as $t) {
            Schema::dropIfExists('aero_medical_' . $t);
        }
    }
};
