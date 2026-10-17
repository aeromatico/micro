<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * tenant_id / customer_id / user_id son enteros sin FK a propósito (como
 * aero/office y aero/sms): el plugin no exige que Sites u Office existan.
 * Los textos clínicos del paciente se guardan cifrados (cast `encrypted`),
 * por eso no hay índices ni búsquedas sobre ellos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aero_medical_settings')) {
            Schema::create('aero_medical_settings', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->unique();
                $t->string('mode', 8)->nullable()->comment('human | animal. Null = el negocio aún no lo eligió');
                $t->boolean('enabled')->default(true);
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('aero_medical_patients')) {
            Schema::create('aero_medical_patients', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->unsignedBigInteger('customer_id')->nullable()->comment('Aero.Office Customer: la persona (humano) o el tutor (animal)');
                $t->string('kind', 8)->default('human')->comment('Modo del negocio al crearlo; no cambia aunque cambie el modo');
                $t->string('name');
                $t->string('document', 64)->nullable();
                $t->date('birth_date')->nullable();
                $t->string('approx_age', 32)->nullable();
                $t->string('sex', 8)->nullable();
                // Solo humano
                $t->string('blood_type', 8)->nullable();
                // Solo animal
                $t->string('species', 32)->nullable();
                $t->string('breed', 64)->nullable();
                $t->boolean('neutered')->nullable();
                $t->string('color_marks')->nullable();
                $t->string('microchip', 64)->nullable();
                $t->decimal('weight_kg', 7, 2)->nullable();
                // Clínico (cifrado en el modelo)
                $t->text('allergies')->nullable();
                $t->text('medications')->nullable();
                $t->text('personal_history')->nullable();
                $t->text('family_history')->nullable();
                $t->text('clinical_notes')->nullable();
                $t->boolean('is_active')->default(true);
                $t->softDeletes();
                $t->timestamps();

                $t->index(['tenant_id', 'name']);
                $t->index(['tenant_id', 'customer_id']);
                $t->index(['tenant_id', 'microchip']);
                $t->index(['tenant_id', 'document']);
            });
        }

        if (!Schema::hasTable('aero_medical_audit_logs')) {
            Schema::create('aero_medical_audit_logs', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->string('user_name')->nullable()->comment('Foto del nombre al momento del acto');
                $t->unsignedBigInteger('patient_id')->nullable();
                $t->string('action', 24)->comment('view|create|update|delete|restore|settings|sign|print|export…');
                $t->string('subject_type', 48)->nullable();
                $t->unsignedBigInteger('subject_id')->nullable();
                $t->text('fields')->nullable()->comment('Solo NOMBRES de campos cambiados, nunca valores clínicos');
                $t->string('ip', 45)->nullable();
                $t->timestamp('created_at')->nullable();

                $t->index(['tenant_id', 'created_at']);
                $t->index(['tenant_id', 'patient_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_medical_audit_logs');
        Schema::dropIfExists('aero_medical_patients');
        Schema::dropIfExists('aero_medical_settings');
    }
};
