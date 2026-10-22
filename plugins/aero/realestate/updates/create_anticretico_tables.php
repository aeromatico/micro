<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/** Contratos de anticrético: capital entregado, devoluciones, renovaciones, documentos y bitácora. Sin FK (como el resto del plugin). */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aero_realestate_contracts')) {
            Schema::create('aero_realestate_contracts', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->string('code', 32)->nullable();
                $t->unsignedBigInteger('property_id')->index();
                $t->unsignedBigInteger('offer_id')->nullable()->index();
                $t->unsignedBigInteger('owner_id')->nullable()->index();
                $t->unsignedBigInteger('client_id')->nullable()->index()->comment('Anticresista');
                $t->unsignedBigInteger('agent_id')->nullable()->index();
                $t->decimal('capital', 14, 2);
                $t->string('currency', 3)->default('USD');
                $t->unsignedSmallInteger('term_months');
                $t->date('start_date')->nullable();
                $t->date('end_date')->nullable();
                $t->string('status', 16)->default('draft')->comment('draft, reserved, active, finished, cancelled');
                $t->text('conditions')->nullable();
                $t->text('return_conditions')->nullable();
                $t->decimal('delivered_amount', 14, 2)->default(0)->comment('Capital entregado (suma de entregas)');
                $t->decimal('returned_amount', 14, 2)->default(0)->comment('Capital devuelto (suma de devoluciones)');
                $t->string('return_status', 16)->default('pending')->comment('pending, partial, completed, dispute');
                $t->boolean('in_dispute')->default(false);
                $t->text('dispute_note')->nullable();
                $t->timestamp('activated_at')->nullable();
                $t->timestamp('finished_at')->nullable();
                $t->smallInteger('last_alert_days')->nullable()->comment('Último umbral de vencimiento avisado');
                $t->timestamp('docs_alert_at')->nullable();
                $t->text('notes')->nullable();
                $t->timestamps();
                $t->softDeletes();
                $t->index(['tenant_id', 'status']);
                $t->index(['tenant_id', 'end_date']);
            });
        }

        if (!Schema::hasTable('aero_realestate_payments')) {
            Schema::create('aero_realestate_payments', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('contract_id')->index();
                $t->string('type', 16)->comment('delivery = anticresista entrega al propietario; return = propietario devuelve');
                $t->decimal('amount', 14, 2);
                $t->string('currency', 3)->default('USD');
                $t->date('paid_on');
                $t->string('method', 16)->nullable()->comment('cash, transfer, check, qr, other');
                $t->string('reference')->nullable();
                $t->text('notes')->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'paid_on']);
            });
        }

        if (!Schema::hasTable('aero_realestate_contract_documents')) {
            Schema::create('aero_realestate_contract_documents', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('contract_id')->index();
                $t->string('type', 24)->comment('public_deed, property_registry, folio, id_document, other');
                $t->string('number', 96)->nullable();
                $t->date('issued_on')->nullable();
                $t->string('status', 16)->default('pending')->comment('pending, verified, rejected');
                $t->unsignedBigInteger('verified_by')->nullable()->comment('Backend user');
                $t->timestamp('verified_at')->nullable();
                $t->text('notes')->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'status']);
            });
        }

        if (!Schema::hasTable('aero_realestate_contract_events')) {
            Schema::create('aero_realestate_contract_events', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('contract_id')->index();
                $t->string('type', 24);
                $t->string('summary');
                $t->json('data')->nullable();
                $t->unsignedBigInteger('user_id')->nullable()->comment('Backend user');
                $t->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['contract_events', 'contract_documents', 'payments', 'contracts'] as $table) {
            Schema::dropIfExists('aero_realestate_' . $table);
        }
    }
};
