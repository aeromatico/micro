<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/** Contratos y documentación: plantillas con revisión legal, contratos generados (arras, alquiler, anticrético), su historial y el archivo documental. Sin FK. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['aero_realestate_clients', 'aero_realestate_owners'] as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'civil_status')) {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    $t->string('civil_status', 32)->nullable();
                    $t->string('nationality', 48)->nullable();
                    if ($table === 'aero_realestate_clients') {
                        $t->string('address')->nullable();
                    }
                });
            }
        }

        if (!Schema::hasTable('aero_realestate_templates')) {
            Schema::create('aero_realestate_templates', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable()->index()->comment('null = modelo base de la plataforma');
                $t->string('type', 16)->comment('arras, rent, anticretico');
                $t->string('name');
                $t->longText('body');
                $t->unsignedSmallInteger('version')->default(1);
                $t->boolean('is_active')->default(true);
                $t->string('legal_status', 12)->default('draft')->comment('draft, reviewed');
                $t->string('reviewed_by_name')->nullable()->comment('Abogado o estudio que revisó');
                $t->unsignedBigInteger('reviewed_by')->nullable()->comment('Backend user que registró la revisión');
                $t->timestamp('reviewed_at')->nullable();
                $t->text('legal_notes')->nullable();
                $t->timestamps();
                $t->softDeletes();
                $t->index(['tenant_id', 'type']);
            });
        }

        if (!Schema::hasTable('aero_realestate_agreements')) {
            Schema::create('aero_realestate_agreements', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->string('code', 32)->nullable();
                $t->string('type', 16);
                $t->unsignedBigInteger('template_id')->nullable()->index();
                $t->unsignedSmallInteger('template_version')->nullable();
                $t->boolean('template_reviewed')->default(false)->comment('La plantilla estaba revisada legalmente al generar');
                $t->unsignedBigInteger('property_id')->index();
                $t->unsignedBigInteger('offer_id')->nullable();
                $t->unsignedBigInteger('owner_id')->nullable()->index();
                $t->unsignedBigInteger('client_id')->nullable()->index();
                $t->unsignedBigInteger('agent_id')->nullable();
                $t->unsignedBigInteger('contract_id')->nullable()->comment('Contrato de anticrético vinculado');
                $t->unsignedBigInteger('lead_id')->nullable();
                $t->string('title');
                $t->longText('body')->nullable();
                $t->json('variables')->nullable();
                $t->decimal('amount', 14, 2)->default(0)->comment('Precio, canon mensual, capital o arras');
                $t->decimal('total_price', 14, 2)->nullable()->comment('Precio total (arras)');
                $t->string('currency', 3)->default('USD');
                $t->decimal('deposit', 14, 2)->nullable();
                $t->unsignedSmallInteger('term_months')->nullable();
                $t->date('start_date')->nullable();
                $t->date('end_date')->nullable();
                $t->string('status', 12)->default('draft')->comment('draft, in_review, approved, signed, finished, cancelled');
                $t->date('signed_on')->nullable();
                $t->string('cancel_reason')->nullable();
                $t->smallInteger('last_alert_days')->nullable();
                $t->timestamps();
                $t->softDeletes();
                $t->index(['tenant_id', 'status']);
                $t->index(['tenant_id', 'end_date']);
            });
        }

        if (!Schema::hasTable('aero_realestate_agreement_events')) {
            Schema::create('aero_realestate_agreement_events', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('agreement_id')->index();
                $t->string('type', 24);
                $t->string('summary');
                $t->json('data')->nullable();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('aero_realestate_documents')) {
            Schema::create('aero_realestate_documents', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('property_id')->nullable()->index();
                $t->unsignedBigInteger('owner_id')->nullable()->index();
                $t->unsignedBigInteger('client_id')->nullable()->index();
                $t->unsignedBigInteger('agreement_id')->nullable()->index();
                $t->string('type', 24);
                $t->string('name');
                $t->string('number', 96)->nullable();
                $t->date('issued_on')->nullable();
                $t->date('expires_on')->nullable();
                $t->string('status', 12)->default('pending')->comment('pending, verified, rejected');
                $t->unsignedBigInteger('verified_by')->nullable();
                $t->timestamp('verified_at')->nullable();
                $t->text('notes')->nullable();
                $t->unsignedBigInteger('uploaded_by')->nullable();
                $t->timestamp('expiry_alerted_at')->nullable();
                $t->timestamps();
                $t->softDeletes();
                $t->index(['tenant_id', 'status']);
                $t->index(['tenant_id', 'expires_on']);
            });
        }
    }

    public function down(): void
    {
        foreach (['documents', 'agreement_events', 'agreements', 'templates'] as $table) {
            Schema::dropIfExists('aero_realestate_' . $table);
        }
        foreach (['aero_realestate_clients', 'aero_realestate_owners'] as $table) {
            if (Schema::hasColumn($table, 'civil_status')) {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    $t->dropColumn($table === 'aero_realestate_clients' ? ['civil_status', 'nationality', 'address'] : ['civil_status', 'nationality']);
                });
            }
        }
    }
};
