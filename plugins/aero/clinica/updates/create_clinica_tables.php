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
        if (!Schema::hasTable('aero_clinica_settings')) {
            Schema::create('aero_clinica_settings', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->unique();
                $t->boolean('enabled')->default(true);
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('aero_clinica_patients')) {
            Schema::create('aero_clinica_patients', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->unsignedBigInteger('customer_id')->nullable()->comment('Aero.Office Customer: la persona o su responsable');
                $t->string('name');
                $t->string('document', 64)->nullable();
                $t->date('birth_date')->nullable();
                $t->string('sex', 8)->nullable();
                $t->string('blood_type', 8)->nullable();
                $t->string('address')->nullable();
                $t->string('city', 96)->nullable();
                $t->string('occupation', 128)->nullable();
                $t->string('marital_status', 16)->nullable();
                $t->string('emergency_name')->nullable();
                $t->string('emergency_phone', 32)->nullable();
                $t->string('emergency_relation', 64)->nullable();
                // Clínico (cifrado en el modelo)
                $t->text('allergies')->nullable();
                $t->text('medications')->nullable();
                $t->text('chronic_conditions')->nullable();
                $t->text('habits')->nullable();
                $t->text('personal_history')->nullable();
                $t->text('family_history')->nullable();
                $t->text('clinical_notes')->nullable();
                $t->boolean('is_active')->default(true);
                $t->softDeletes();
                $t->timestamps();

                $t->index(['tenant_id', 'name']);
                $t->index(['tenant_id', 'customer_id']);
                $t->index(['tenant_id', 'document']);
            });
        }

        if (!Schema::hasTable('aero_clinica_audit_logs')) {
            Schema::create('aero_clinica_audit_logs', function (Blueprint $t) {
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
        Schema::dropIfExists('aero_clinica_audit_logs');
        Schema::dropIfExists('aero_clinica_patients');
        Schema::dropIfExists('aero_clinica_settings');
    }
};
