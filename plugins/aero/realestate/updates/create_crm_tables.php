<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/** CRM inmobiliario: ajustes, leads con etapas, historial, visitas, reglas de comisión y comisiones. Sin FK (como el resto del plugin). */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aero_realestate_settings')) {
            Schema::create('aero_realestate_settings', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->unique();
                $t->string('business_name')->nullable();
                $t->unsignedSmallInteger('visit_minutes')->default(45)->comment('Duración por defecto de una visita');
                $t->unsignedSmallInteger('reminder_hours')->default(24)->comment('0 = sin recordatorio de visita');
                $t->boolean('notify_clients')->default(true);
                $t->boolean('notify_agents')->default(true);
                $t->unsignedSmallInteger('followup_days')->default(3)->comment('Días hasta el próximo seguimiento comercial');
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('aero_realestate_leads')) {
            Schema::create('aero_realestate_leads', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('client_id')->index();
                $t->unsignedBigInteger('property_id')->nullable()->index();
                $t->unsignedBigInteger('offer_id')->nullable()->index();
                $t->unsignedBigInteger('agent_id')->nullable()->index();
                $t->string('modality', 16)->nullable()->comment('sale, rent, anticretico');
                $t->string('stage', 20)->default('new')->comment('new, contacted, visit_scheduled, negotiation, closed_won, closed_lost');
                $t->string('source', 32)->nullable();
                $t->decimal('final_amount', 14, 2)->nullable();
                $t->string('lost_reason')->nullable();
                $t->text('notes')->nullable();
                $t->timestamp('last_contact_at')->nullable();
                $t->timestamp('next_follow_up_at')->nullable();
                $t->timestamp('follow_up_alerted_at')->nullable();
                $t->timestamp('closed_at')->nullable();
                $t->timestamps();
                $t->softDeletes();
                $t->index(['tenant_id', 'stage']);
                $t->index(['tenant_id', 'next_follow_up_at']);
            });
        }

        if (!Schema::hasTable('aero_realestate_interactions')) {
            Schema::create('aero_realestate_interactions', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('client_id')->index();
                $t->unsignedBigInteger('lead_id')->nullable()->index();
                $t->string('type', 16)->comment('note, call, whatsapp, email, visit, stage, deal');
                $t->text('body');
                $t->unsignedBigInteger('user_id')->nullable()->comment('Backend user');
                $t->timestamp('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('aero_realestate_visits')) {
            Schema::create('aero_realestate_visits', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->string('code', 32)->nullable();
                $t->unsignedBigInteger('property_id')->index();
                $t->unsignedBigInteger('client_id')->index();
                $t->unsignedBigInteger('lead_id')->nullable()->index();
                $t->unsignedBigInteger('agent_id')->nullable()->index();
                $t->timestamp('starts_at');
                $t->timestamp('ends_at');
                $t->string('status', 16)->default('requested')->comment('requested, confirmed, rescheduled, done, cancelled');
                $t->string('source', 16)->default('panel')->comment('panel, portal');
                $t->text('notes')->nullable();
                $t->string('cancel_reason')->nullable();
                $t->timestamp('reminder_sent_at')->nullable();
                $t->timestamps();
                $t->softDeletes();
                $t->index(['tenant_id', 'starts_at']);
                $t->index(['agent_id', 'starts_at']);
                $t->index(['status', 'starts_at', 'reminder_sent_at']);
            });
        }

        if (!Schema::hasTable('aero_realestate_commission_rules')) {
            Schema::create('aero_realestate_commission_rules', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->string('modality', 16);
                $t->unsignedBigInteger('agent_id')->nullable()->index()->comment('null = regla general de la modalidad');
                $t->string('basis', 16)->default('percent')->comment('percent del monto, monthly_rents = N cánones, fixed');
                $t->decimal('value', 12, 2);
                $t->decimal('agent_share_percent', 5, 2)->default(50)->comment('Parte de la comisión que recibe el agente');
                $t->boolean('is_active')->default(true);
                $t->timestamps();
                $t->index(['tenant_id', 'modality']);
            });
        }

        if (!Schema::hasTable('aero_realestate_commissions')) {
            Schema::create('aero_realestate_commissions', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('lead_id')->nullable()->index();
                $t->unsignedBigInteger('offer_id')->nullable()->index();
                $t->unsignedBigInteger('contract_id')->nullable()->index();
                $t->unsignedBigInteger('property_id')->nullable()->index();
                $t->unsignedBigInteger('client_id')->nullable();
                $t->unsignedBigInteger('agent_id')->nullable()->index();
                $t->string('modality', 16);
                $t->decimal('base_amount', 14, 2);
                $t->string('currency', 3)->default('USD');
                $t->string('rule_text')->nullable()->comment('Regla aplicada, como quedó al cerrar');
                $t->decimal('commission_amount', 14, 2)->default(0)->comment('Comisión total de la inmobiliaria');
                $t->decimal('agent_share_percent', 5, 2)->default(0);
                $t->decimal('agent_amount', 14, 2)->default(0);
                $t->string('status', 16)->default('pending')->comment('pending, approved, paid, cancelled');
                $t->date('paid_on')->nullable();
                $t->text('notes')->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'status']);
                $t->index(['tenant_id', 'agent_id']);
            });
        }
    }

    public function down(): void
    {
        foreach (['commissions', 'commission_rules', 'visits', 'interactions', 'leads', 'settings'] as $table) {
            Schema::dropIfExists('aero_realestate_' . $table);
        }
    }
};
