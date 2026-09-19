<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Departamentos y tickets. tenant_id NULL = mesa de ayuda de la plataforma
 * (sitio principal); con valor = mesa de ayuda de ese tenant. Mismo esquema
 * para ambos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_crm_departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('aero_sites_tenants')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('aero_crm_department_user', function (Blueprint $table) {
            $table->foreignId('department_id')->constrained('aero_crm_departments')->cascadeOnDelete();
            $table->unsignedInteger('user_id');
            $table->boolean('is_lead')->default(false);

            $table->primary(['department_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('aero_crm_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('aero_sites_tenants')->cascadeOnDelete();
            $table->unsignedInteger('seq');
            $table->string('subject');
            $table->text('description')->nullable();
            $table->foreignId('department_id')->nullable()->constrained('aero_crm_departments')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('aero_crm_contacts')->nullOnDelete();
            $table->string('requester_name')->nullable();
            $table->string('requester_email')->nullable();
            $table->string('requester_phone', 30)->nullable();
            $table->unsignedInteger('assigned_to')->nullable();
            $table->string('status', 20)->default('open');
            $table->string('priority', 20)->default('normal');
            $table->string('source', 20)->default('manual');
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'seq']);
            $table->index(['tenant_id', 'status']);
            $table->index('department_id');
            $table->index('assigned_to');
        });

        Schema::create('aero_crm_ticket_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('aero_crm_tickets')->cascadeOnDelete();
            $table->unsignedInteger('user_id')->nullable();
            $table->text('body');
            $table->boolean('is_internal')->default(false);
            $table->timestamps();

            $table->index('ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_crm_ticket_replies');
        Schema::dropIfExists('aero_crm_tickets');
        Schema::dropIfExists('aero_crm_department_user');
        Schema::dropIfExists('aero_crm_departments');
    }
};
