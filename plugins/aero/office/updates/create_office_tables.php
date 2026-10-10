<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * tenant_id / user_id / *_id de otros plugins son enteros sin FK a propósito
 * (como aero/sms y aero/gym): el plugin no exige que Sites, CRM o Pay existan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aero_office_settings')) {
            Schema::create('aero_office_settings', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->unique();
                $t->string('business_name')->nullable();
                $t->string('industry', 64)->nullable()->comment('Rubro libre: consultorio, veterinaria, barbería…');
                $t->text('description')->nullable();
                $t->string('logo_url')->nullable();
                $t->string('phone', 32)->nullable();
                $t->string('email')->nullable();
                $t->string('address')->nullable();
                $t->decimal('lat', 10, 7)->nullable();
                $t->decimal('lng', 10, 7)->nullable();
                $t->string('brand_color', 9)->nullable();
                $t->string('currency', 8)->default('BOB');
                $t->boolean('enabled')->default(true)->comment('Interruptor general del módulo');
                $t->boolean('public_enabled')->default(false)->comment('Publica la página de reservas');
                $t->unsignedInteger('min_notice_hours')->default(2)->comment('Anticipación mínima para reservar');
                $t->unsignedInteger('max_advance_days')->default(60)->comment('Hasta cuántos días adelante se puede reservar');
                $t->unsignedSmallInteger('slot_step_minutes')->default(15);
                $t->unsignedInteger('cancel_window_hours')->default(2)->comment('El cliente puede cancelar hasta X horas antes');
                $t->boolean('auto_assign_worker')->default(true)->comment('Permite «cualquier profesional disponible»');
                $t->text('booking_terms')->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('aero_office_branches')) {
            Schema::create('aero_office_branches', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->string('name');
                $t->string('address')->nullable();
                $t->string('phone', 32)->nullable();
                $t->decimal('lat', 10, 7)->nullable();
                $t->decimal('lng', 10, 7)->nullable();
                $t->json('hours')->nullable()->comment('[{weekday,start_time,end_time}]');
                $t->boolean('is_active')->default(true);
                $t->timestamps();
                $t->index(['tenant_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('aero_office_services')) {
            Schema::create('aero_office_services', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->string('name');
                $t->string('category', 96)->nullable();
                $t->text('description')->nullable();
                $t->unsignedInteger('duration_minutes')->default(30);
                $t->unsignedInteger('buffer_minutes')->default(0)->comment('Descanso después de la atención');
                $t->decimal('price', 10, 2)->default(0);
                $t->boolean('requires_approval')->default(false);
                $t->boolean('is_active')->default(true);
                $t->boolean('is_public')->default(true)->comment('false = oculto del portal (solo manual)');
                $t->timestamps();
                $t->index(['tenant_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('aero_office_workers')) {
            Schema::create('aero_office_workers', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('user_id')->nullable()->comment('Backend\\User, opcional: puede no tener cuenta');
                $t->string('name');
                $t->string('title')->nullable();
                $t->string('phone', 32)->nullable();
                $t->string('email')->nullable();
                $t->text('bio')->nullable();
                $t->json('hours')->nullable()->comment('[{weekday,start_time,end_time}]; vacío = el de la sucursal');
                $t->boolean('is_active')->default(true);
                $t->boolean('is_public')->default(true);
                $t->timestamps();
                $t->index(['tenant_id', 'created_at']);
                $t->index(['tenant_id', 'user_id']);
            });
        }

        if (!Schema::hasTable('aero_office_branch_service')) {
            Schema::create('aero_office_branch_service', function (Blueprint $t) {
                $t->unsignedBigInteger('branch_id');
                $t->unsignedBigInteger('service_id');
                $t->primary(['branch_id', 'service_id']);
            });
        }

        if (!Schema::hasTable('aero_office_service_worker')) {
            Schema::create('aero_office_service_worker', function (Blueprint $t) {
                $t->unsignedBigInteger('service_id');
                $t->unsignedBigInteger('worker_id');
                $t->primary(['service_id', 'worker_id']);
            });
        }

        if (!Schema::hasTable('aero_office_branch_worker')) {
            Schema::create('aero_office_branch_worker', function (Blueprint $t) {
                $t->unsignedBigInteger('branch_id');
                $t->unsignedBigInteger('worker_id');
                $t->primary(['branch_id', 'worker_id']);
            });
        }

        if (!Schema::hasTable('aero_office_time_offs')) {
            Schema::create('aero_office_time_offs', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('worker_id')->nullable()->comment('null = aplica a todos');
                $t->unsignedBigInteger('branch_id')->nullable()->comment('null = todas las sucursales');
                $t->string('type', 16)->default('absence')->comment('holiday, vacation, break, absence');
                $t->dateTime('starts_at');
                $t->dateTime('ends_at');
                $t->string('reason')->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'starts_at']);
                $t->index(['worker_id', 'starts_at']);
            });
        }

        if (!Schema::hasTable('aero_office_customers')) {
            Schema::create('aero_office_customers', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('user_id')->nullable()->comment('RainLab.User, opcional');
                $t->unsignedBigInteger('crm_contact_id')->nullable()->comment('Aero.Crm');
                $t->string('name');
                $t->string('phone', 32)->nullable();
                $t->string('email')->nullable();
                $t->string('document', 32)->nullable();
                $t->text('notes')->nullable();
                $t->timestamps();
                $t->softDeletes();
                $t->index(['tenant_id', 'created_at']);
                $t->index(['tenant_id', 'phone']);
                $t->index(['tenant_id', 'email']);
                $t->index(['tenant_id', 'user_id']);
            });
        }

        if (!Schema::hasTable('aero_office_bookings')) {
            Schema::create('aero_office_bookings', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->string('code', 12)->unique()->comment('Código corto visible al cliente');
                $t->string('manage_token', 48)->unique()->comment('Autoriza al cliente a ver/cancelar/reprogramar sin cuenta');
                $t->unsignedBigInteger('branch_id');
                $t->unsignedBigInteger('service_id');
                $t->unsignedBigInteger('worker_id');
                $t->unsignedBigInteger('customer_id');
                $t->dateTime('starts_at');
                $t->dateTime('ends_at');
                $t->dateTime('blocks_until')->comment('ends_at + descanso: lo que ocupa en la agenda');
                $t->string('status', 16)->default('pending')
                    ->comment('pending, confirmed, in_service, completed, cancelled, rejected, no_show');
                $t->string('source', 16)->default('manual')->comment('public, manual');
                $t->string('service_name')->comment('Instantánea: los cambios del servicio no tocan lo histórico');
                $t->unsignedInteger('duration_minutes');
                $t->decimal('price', 10, 2)->default(0);
                $t->string('currency', 8)->default('BOB');
                $t->string('worker_name')->nullable();
                $t->text('customer_notes')->nullable();
                $t->text('internal_notes')->nullable();
                $t->timestamp('confirmed_at')->nullable();
                $t->timestamp('cancelled_at')->nullable();
                $t->string('cancel_reason')->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'starts_at']);
                $t->index(['worker_id', 'starts_at', 'blocks_until']);
                $t->index(['tenant_id', 'status', 'starts_at']);
                $t->index(['customer_id', 'starts_at']);
            });
        }

        if (!Schema::hasTable('aero_office_booking_logs')) {
            Schema::create('aero_office_booking_logs', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('booking_id');
                $t->unsignedBigInteger('user_id')->nullable()->comment('Quien hizo el cambio (null = cliente/sistema)');
                $t->string('actor', 16)->default('system')->comment('staff, customer, system');
                $t->string('action', 24);
                $t->json('changes')->nullable();
                $t->string('note')->nullable();
                $t->timestamp('created_at')->nullable();
                $t->index(['booking_id', 'created_at']);
                $t->index(['tenant_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        foreach (['booking_logs', 'bookings', 'customers', 'time_offs', 'branch_worker', 'service_worker', 'branch_service',
                  'workers', 'services', 'branches', 'settings'] as $t) {
            Schema::dropIfExists('aero_office_' . $t);
        }
    }
};
