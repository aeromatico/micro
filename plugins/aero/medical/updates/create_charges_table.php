<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Cobros de la atención. Registro administrativo (concepto, monto, estado): nada clínico va aquí ni a
 * Finanzas/Pay. Los cobros por QR se emiten con Aero.Pay (QrIssuer) y se concilian por su evento.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aero_medical_charges')) {
            Schema::create('aero_medical_charges', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->unsignedBigInteger('patient_id');
                $t->unsignedBigInteger('consultation_id')->nullable();
                $t->unsignedBigInteger('booking_id')->nullable();
                $t->text('concept')->comment('Cifrado');
                $t->decimal('amount', 12, 2);
                $t->string('currency', 3)->default('BOB');
                $t->string('status', 10)->default('pending')->comment('pending | paid | cancelled');
                $t->string('method', 10)->default('cash')->comment('cash | transfer | qr | other');
                $t->unsignedBigInteger('qr_code_id')->nullable();
                $t->unsignedBigInteger('bank_account_id')->nullable();
                $t->unsignedBigInteger('registered_by')->nullable();
                $t->timestamp('paid_at')->nullable();
                $t->string('cancel_reason')->nullable();
                $t->timestamps();

                $t->index(['tenant_id', 'status'], 'amch_status');
                $t->index(['tenant_id', 'patient_id'], 'amch_patient');
                $t->index('qr_code_id', 'amch_qr');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_medical_charges');
    }
};
