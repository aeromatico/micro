<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * tenant_id / user_id / *_id de otros plugins son enteros sin FK a propósito
 * (como aero/sms): el plugin no exige que Sites, Pay o Hello existan.
 */
return new class extends Migration
{
    protected function tenant(Blueprint $t): void
    {
        $t->unsignedBigInteger('tenant_id')->nullable();
    }

    public function up(): void
    {
        if (!Schema::hasTable('aero_school_settings')) {
            Schema::create('aero_school_settings', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->unique();
                $t->boolean('enabled')->default(true);
                $t->string('school_name')->nullable();
                $t->string('sie_code', 32)->nullable()->comment('Código SIE de la unidad educativa');
                $t->string('district')->nullable();
                $t->string('city')->nullable();
                $t->string('director')->nullable();
                $t->string('logo_url', 500)->nullable();
                $t->string('currency', 8)->default('BOB');
                $t->unsignedBigInteger('bank_account_id')->nullable()->comment('Aero.Pay');
                $t->unsignedTinyInteger('passing_mark')->default(51);
                $t->boolean('reminders_enabled')->default(true);
                $t->string('reminder_days', 64)->default('3,0')->comment('Días antes del vencimiento');
                $t->unsignedSmallInteger('overdue_every_days')->default(7);
                $t->unsignedTinyInteger('overdue_max')->default(4);
                $t->decimal('late_fee', 10, 2)->default(0)->comment('Recargo fijo por cuota vencida (0 = sin recargo)');
                $t->boolean('notify_absence')->default(true);
                $t->boolean('notify_late')->default(false);
                $t->text('reminder_template')->nullable();
                $t->text('overdue_template')->nullable();
                $t->text('absence_template')->nullable();
                $t->boolean('portal_grades')->default(true)->comment('El padre ve notas y boletín en el portal');
                $t->boolean('portal_block_if_debt')->default(false)->comment('Oculta el boletín mientras haya deuda vencida');
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('aero_school_years')) {
            Schema::create('aero_school_years', function (Blueprint $t) {
                $t->id();
                $this->tenant($t);
                $t->string('name', 32);
                $t->date('starts_on')->nullable();
                $t->date('ends_on')->nullable();
                $t->boolean('is_current')->default(false);
                $t->unsignedTinyInteger('periods')->default(3)->comment('Trimestres');
                $t->timestamps();
                $t->index(['tenant_id', 'is_current']);
            });
        }

        if (!Schema::hasTable('aero_school_courses')) {
            Schema::create('aero_school_courses', function (Blueprint $t) {
                $t->id();
                $this->tenant($t);
                $t->unsignedBigInteger('year_id')->nullable();
                $t->string('name', 64);
                $t->string('level', 16)->default('primaria')->comment('inicial, primaria, secundaria');
                $t->unsignedTinyInteger('grade')->default(1);
                $t->string('parallel', 8)->nullable();
                $t->string('shift', 16)->default('manana')->comment('manana, tarde, noche');
                $t->string('tutor')->nullable();
                $t->unsignedSmallInteger('capacity')->nullable();
                $t->boolean('is_active')->default(true);
                $t->timestamps();
                $t->index(['tenant_id', 'year_id']);
            });
        }

        if (!Schema::hasTable('aero_school_subjects')) {
            Schema::create('aero_school_subjects', function (Blueprint $t) {
                $t->id();
                $this->tenant($t);
                $t->unsignedBigInteger('course_id');
                $t->string('name');
                $t->string('field', 64)->nullable()->comment('Campo de saberes y conocimientos');
                $t->string('teacher')->nullable();
                $t->unsignedSmallInteger('sort_order')->default(0);
                $t->timestamps();
                $t->index(['tenant_id', 'course_id']);
            });
        }

        if (!Schema::hasTable('aero_school_guardians')) {
            Schema::create('aero_school_guardians', function (Blueprint $t) {
                $t->id();
                $this->tenant($t);
                $t->unsignedBigInteger('user_id')->nullable()->comment('RainLab.User');
                $t->string('name');
                $t->string('document', 32)->nullable();
                $t->string('phone', 32)->nullable();
                $t->string('email')->nullable();
                $t->string('address')->nullable();
                $t->string('occupation')->nullable();
                $t->text('notes')->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'created_at']);
                $t->index('user_id');
            });
        }

        if (!Schema::hasTable('aero_school_students')) {
            Schema::create('aero_school_students', function (Blueprint $t) {
                $t->id();
                $this->tenant($t);
                $t->string('rude', 32)->nullable()->comment('Código RUDE');
                $t->string('document', 32)->nullable();
                $t->string('first_name');
                $t->string('paternal_surname')->nullable();
                $t->string('maternal_surname')->nullable();
                $t->date('birthdate')->nullable();
                $t->string('sex', 1)->nullable();
                $t->unsignedBigInteger('course_id')->nullable();
                $t->string('status', 16)->default('active')->comment('active, retired, graduated');
                $t->decimal('discount_pct', 5, 2)->default(0)->comment('Beca / descuento sobre cada cuota');
                $t->string('discount_note')->nullable();
                $t->date('enrolled_on')->nullable();
                $t->text('notes')->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'course_id']);
                $t->index(['tenant_id', 'status']);
            });
        }

        if (!Schema::hasTable('aero_school_student_guardian')) {
            Schema::create('aero_school_student_guardian', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('student_id');
                $t->unsignedBigInteger('guardian_id');
                $t->string('relationship', 32)->nullable();
                $t->boolean('is_primary')->default(false);
                $t->boolean('is_financial')->default(true)->comment('Recibe los avisos de cobranza');
                $t->unique(['student_id', 'guardian_id']);
                $t->index('guardian_id');
            });
        }

        if (!Schema::hasTable('aero_school_fee_plans')) {
            Schema::create('aero_school_fee_plans', function (Blueprint $t) {
                $t->id();
                $this->tenant($t);
                $t->unsignedBigInteger('year_id')->nullable();
                $t->string('name');
                $t->string('kind', 16)->default('pension')->comment('matricula, pension, otro');
                $t->decimal('amount', 10, 2)->default(0);
                $t->unsignedBigInteger('course_id')->nullable()->comment('null = según nivel/todos');
                $t->string('level', 16)->nullable()->comment('null = todos');
                $t->unsignedTinyInteger('installments')->default(1)->comment('Número de cuotas');
                $t->unsignedTinyInteger('first_month')->default(2)->comment('Mes de la primera cuota (1-12)');
                $t->unsignedTinyInteger('due_day')->default(10);
                $t->boolean('is_active')->default(true);
                $t->timestamps();
                $t->index(['tenant_id', 'year_id']);
            });
        }

        if (!Schema::hasTable('aero_school_charges')) {
            Schema::create('aero_school_charges', function (Blueprint $t) {
                $t->id();
                $this->tenant($t);
                $t->unsignedBigInteger('student_id');
                $t->unsignedBigInteger('year_id')->nullable();
                $t->unsignedBigInteger('fee_plan_id')->nullable();
                $t->string('kind', 16)->default('pension');
                $t->string('concept');
                $t->string('period', 7)->nullable()->comment('YYYY-MM');
                $t->date('due_on')->nullable();
                $t->decimal('amount', 10, 2)->default(0);
                $t->decimal('discount', 10, 2)->default(0);
                $t->decimal('paid_amount', 10, 2)->default(0);
                $t->string('status', 16)->default('pending')->comment('pending, partial, paid, void');
                $t->timestamp('paid_at')->nullable();
                $t->string('payment_reference', 64)->nullable()->comment('Aero.Pay: referencia del QR');
                $t->json('reminders_sent')->nullable();
                $t->string('notes')->nullable();
                $t->timestamps();
                $t->unique(['student_id', 'fee_plan_id', 'period'], 'school_charge_unique');
                $t->index(['tenant_id', 'status', 'due_on']);
                $t->index('student_id');
            });
        }

        if (!Schema::hasTable('aero_school_payments')) {
            Schema::create('aero_school_payments', function (Blueprint $t) {
                $t->id();
                $this->tenant($t);
                $t->unsignedBigInteger('student_id');
                $t->unsignedBigInteger('guardian_id')->nullable();
                $t->unsignedInteger('receipt_no')->nullable();
                $t->decimal('amount', 10, 2)->default(0);
                $t->string('method', 16)->default('cash')->comment('cash, qr, transfer, deposit');
                $t->string('reference')->nullable();
                $t->timestamp('paid_at')->nullable();
                $t->unsignedBigInteger('received_by')->nullable()->comment('Usuario del backend');
                $t->string('notes')->nullable();
                $t->timestamp('voided_at')->nullable();
                $t->timestamps();
                $t->unique(['tenant_id', 'receipt_no']);
                $t->index(['tenant_id', 'paid_at']);
                $t->index('student_id');
            });
        }

        if (!Schema::hasTable('aero_school_payment_items')) {
            Schema::create('aero_school_payment_items', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('payment_id');
                $t->unsignedBigInteger('charge_id');
                $t->decimal('amount', 10, 2);
                $t->index('payment_id');
                $t->index('charge_id');
            });
        }

        if (!Schema::hasTable('aero_school_attendances')) {
            Schema::create('aero_school_attendances', function (Blueprint $t) {
                $t->id();
                $this->tenant($t);
                $t->unsignedBigInteger('student_id');
                $t->unsignedBigInteger('course_id');
                $t->date('date');
                $t->string('status', 16)->default('present')->comment('present, absent, late, excused');
                $t->string('note')->nullable();
                $t->timestamp('notified_at')->nullable();
                $t->unsignedBigInteger('recorded_by')->nullable();
                $t->timestamps();
                $t->unique(['student_id', 'date']);
                $t->index(['tenant_id', 'course_id', 'date']);
            });
        }

        if (!Schema::hasTable('aero_school_grades')) {
            Schema::create('aero_school_grades', function (Blueprint $t) {
                $t->id();
                $this->tenant($t);
                $t->unsignedBigInteger('student_id');
                $t->unsignedBigInteger('subject_id');
                $t->unsignedBigInteger('course_id');
                $t->unsignedBigInteger('year_id')->nullable();
                $t->unsignedTinyInteger('period')->default(1);
                $t->decimal('ser', 5, 2)->nullable()->comment('máx 10');
                $t->decimal('saber', 5, 2)->nullable()->comment('máx 35');
                $t->decimal('hacer', 5, 2)->nullable()->comment('máx 35');
                $t->decimal('decidir', 5, 2)->nullable()->comment('máx 10');
                $t->decimal('auto_ser', 5, 2)->nullable()->comment('máx 5');
                $t->decimal('auto_decidir', 5, 2)->nullable()->comment('máx 5');
                $t->decimal('total', 5, 2)->nullable()->comment('0-100');
                $t->string('qualitative', 255)->nullable()->comment('Valoración cualitativa (Inicial)');
                $t->timestamps();
                $t->unique(['student_id', 'subject_id', 'period']);
                $t->index(['tenant_id', 'course_id', 'period']);
            });
        }

        if (!Schema::hasTable('aero_school_announcements')) {
            Schema::create('aero_school_announcements', function (Blueprint $t) {
                $t->id();
                $this->tenant($t);
                $t->string('title');
                $t->text('body');
                $t->string('audience', 16)->default('all')->comment('all, level, course');
                $t->string('level', 16)->nullable();
                $t->unsignedBigInteger('course_id')->nullable();
                $t->string('attachment_url', 500)->nullable();
                $t->string('status', 16)->default('draft')->comment('draft, sending, sent');
                $t->timestamp('sent_at')->nullable();
                $t->unsignedInteger('total')->default(0);
                $t->unsignedInteger('delivered')->default(0);
                $t->unsignedInteger('failed')->default(0);
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('aero_school_deliveries')) {
            Schema::create('aero_school_deliveries', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('announcement_id');
                $t->unsignedBigInteger('guardian_id');
                $t->string('phone', 32)->nullable();
                $t->string('status', 16)->default('sent')->comment('sent, failed');
                $t->string('error')->nullable();
                $t->timestamp('created_at')->nullable();
                $t->index('announcement_id');
            });
        }
    }

    public function down(): void
    {
        foreach (['deliveries', 'announcements', 'grades', 'attendances', 'payment_items', 'payments', 'charges', 'fee_plans',
            'student_guardian', 'students', 'guardians', 'subjects', 'courses', 'years', 'settings'] as $t) {
            Schema::dropIfExists('aero_school_' . $t);
        }
    }
};
