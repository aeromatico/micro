<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * tenant_id / user_id / *_id de otros plugins son enteros sin FK a propósito
 * (como aero/sms): el plugin no exige que Sites, Pay o Shop existan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aero_gym_settings')) {
            Schema::create('aero_gym_settings', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->unique();
                $t->unsignedBigInteger('bank_account_id')->nullable()->comment('Aero.Pay: cuenta para cobrar membresías');
                $t->string('currency', 8)->default('BOB');
                $t->string('reminder_days')->default('3,1,0')->comment('Días antes del vencimiento, separados por coma');
                $t->boolean('reminders_enabled')->default(true);
                $t->unsignedSmallInteger('grace_days')->default(0)->comment('Días de gracia con acceso tras vencer');
                $t->unsignedSmallInteger('cancel_window_hours')->default(2)->comment('Horas antes de la clase hasta las que se puede cancelar');
                $t->boolean('waitlist_auto_promote')->default(true);
                $t->unsignedBigInteger('shop_collection_id')->nullable()->comment('Aero.Shop: colección de productos del gimnasio');
                $t->text('reminder_template')->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('aero_gym_plans')) {
            Schema::create('aero_gym_plans', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->string('name');
                $t->text('description')->nullable();
                $t->decimal('price', 10, 2)->default(0);
                $t->unsignedInteger('duration_days')->default(30);
                $t->unsignedSmallInteger('classes_per_week')->nullable()->comment('null = ilimitado');
                $t->unsignedBigInteger('shop_product_id')->nullable()->comment('Aero.Shop: producto equivalente');
                $t->boolean('is_active')->default(true);
                $t->timestamps();
                $t->index(['tenant_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('aero_gym_members')) {
            Schema::create('aero_gym_members', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('user_id')->nullable()->comment('RainLab.User');
                $t->string('name');
                $t->string('phone', 32)->nullable();
                $t->string('email')->nullable();
                $t->string('document', 32)->nullable();
                $t->date('birthdate')->nullable();
                $t->string('card_number', 32)->nullable();
                $t->string('qr_token', 40)->unique();
                $t->string('status', 16)->default('active')->comment('active, suspended, inactive');
                $t->text('notes')->nullable();
                $t->timestamps();
                $t->softDeletes();
                $t->index(['tenant_id', 'created_at']);
                $t->index(['tenant_id', 'card_number']);
                $t->index(['tenant_id', 'user_id']);
            });
        }

        if (!Schema::hasTable('aero_gym_memberships')) {
            Schema::create('aero_gym_memberships', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('member_id');
                $t->unsignedBigInteger('plan_id')->nullable();
                $t->date('starts_on');
                $t->date('ends_on');
                $t->string('status', 16)->default('pending')->comment('pending, active, expired, cancelled');
                $t->decimal('price', 10, 2)->default(0);
                $t->string('currency', 8)->default('BOB');
                $t->timestamp('paid_at')->nullable();
                $t->string('payment_reference')->nullable()->comment('internal_reference del QR de Aero.Pay');
                $t->unsignedBigInteger('renewed_from_id')->nullable();
                $t->json('reminders_sent')->nullable();
                $t->timestamp('cancelled_at')->nullable();
                $t->string('cancel_reason')->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'created_at']);
                $t->index(['member_id', 'status', 'ends_on']);
                $t->index(['tenant_id', 'status', 'ends_on']);
            });
        }

        if (!Schema::hasTable('aero_gym_groups')) {
            Schema::create('aero_gym_groups', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->string('name');
                $t->string('purpose')->nullable()->comment('Para qué sirve: entrenamiento, comunicación, convenio…');
                $t->text('description')->nullable();
                $t->boolean('is_active')->default(true);
                $t->timestamps();
                $t->index(['tenant_id', 'created_at']);
            });

            Schema::create('aero_gym_group_member', function (Blueprint $t) {
                $t->unsignedBigInteger('group_id');
                $t->unsignedBigInteger('member_id');
                $t->primary(['group_id', 'member_id']);
            });
        }

        if (!Schema::hasTable('aero_gym_instructors')) {
            Schema::create('aero_gym_instructors', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->string('name');
                $t->string('phone', 32)->nullable();
                $t->string('email')->nullable();
                $t->text('bio')->nullable();
                $t->boolean('is_active')->default(true);
                $t->timestamps();
                $t->index(['tenant_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('aero_gym_class_types')) {
            Schema::create('aero_gym_class_types', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->string('name');
                $t->text('description')->nullable();
                $t->unsignedSmallInteger('duration_minutes')->default(60);
                $t->unsignedSmallInteger('default_capacity')->default(20);
                $t->string('color', 9)->nullable();
                $t->unsignedBigInteger('page_id')->nullable()->comment('Aero.Sites: página que explica/promociona la clase');
                $t->boolean('is_active')->default(true);
                $t->timestamps();
                $t->index(['tenant_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('aero_gym_schedules')) {
            Schema::create('aero_gym_schedules', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('class_type_id');
                $t->unsignedBigInteger('instructor_id')->nullable();
                $t->unsignedTinyInteger('weekday')->comment('1=lunes … 7=domingo (ISO)');
                $t->time('start_time');
                $t->unsignedSmallInteger('capacity')->nullable()->comment('null = el de la clase');
                $t->string('room')->nullable();
                $t->boolean('is_active')->default(true);
                $t->timestamps();
                $t->index(['tenant_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('aero_gym_sessions')) {
            Schema::create('aero_gym_sessions', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('class_type_id');
                $t->unsignedBigInteger('instructor_id')->nullable();
                $t->unsignedBigInteger('schedule_id')->nullable();
                $t->dateTime('starts_at');
                $t->dateTime('ends_at');
                $t->unsignedSmallInteger('capacity')->default(20);
                $t->string('room')->nullable();
                $t->string('status', 16)->default('scheduled')->comment('scheduled, cancelled, done');
                $t->timestamps();
                $t->index(['tenant_id', 'starts_at']);
                $t->unique(['schedule_id', 'starts_at']);
            });
        }

        if (!Schema::hasTable('aero_gym_bookings')) {
            Schema::create('aero_gym_bookings', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('session_id');
                $t->unsignedBigInteger('member_id');
                $t->string('status', 16)->default('booked')->comment('booked, waitlist, cancelled, attended, no_show');
                $t->timestamp('booked_at')->nullable();
                $t->timestamp('cancelled_at')->nullable();
                $t->timestamp('checked_in_at')->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'created_at']);
                $t->index(['session_id', 'status']);
                $t->index(['member_id', 'status']);
            });
        }

        if (!Schema::hasTable('aero_gym_access_logs')) {
            Schema::create('aero_gym_access_logs', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('member_id')->nullable();
                $t->string('credential', 40)->nullable();
                $t->string('method', 16)->default('qr')->comment('qr, card, manual');
                $t->boolean('granted')->default(false);
                $t->string('reason')->nullable();
                $t->unsignedBigInteger('session_id')->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'created_at']);
                $t->index(['member_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('aero_gym_routines')) {
            Schema::create('aero_gym_routines', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('member_id')->nullable()->comment('null = plantilla reutilizable');
                $t->unsignedBigInteger('instructor_id')->nullable();
                $t->string('name');
                $t->string('goal')->nullable();
                $t->date('starts_on')->nullable();
                $t->date('ends_on')->nullable();
                $t->text('notes')->nullable();
                $t->boolean('is_active')->default(true);
                $t->timestamps();
                $t->index(['tenant_id', 'created_at']);
                $t->index(['member_id']);
            });

            Schema::create('aero_gym_routine_items', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('routine_id');
                $t->string('day_label', 40)->nullable();
                $t->string('exercise');
                $t->unsignedSmallInteger('sets')->nullable();
                $t->string('reps', 32)->nullable();
                $t->unsignedSmallInteger('rest_seconds')->nullable();
                $t->string('notes')->nullable();
                $t->unsignedInteger('sort_order')->default(0);
                $t->index(['routine_id', 'sort_order']);
            });
        }
    }

    public function down(): void
    {
        foreach (['routine_items', 'routines', 'access_logs', 'bookings', 'sessions', 'schedules', 'class_types', 'instructors', 'group_member', 'groups', 'memberships', 'members', 'plans', 'settings'] as $t) {
            Schema::dropIfExists('aero_gym_' . $t);
        }
    }
};
