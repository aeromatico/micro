<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * tenant_id es un entero sin FK a propósito: el plugin no exige Aero.Sites.
 * Aquí NO se guardan certificados ni tokens del SIN: con el driver Floogos
 * viven en la credencial `siat` del proveedor y solo guardamos su referencia.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aero_invoicing_issuers')) {
            Schema::create('aero_invoicing_issuers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('name')->comment('Etiqueta interna');
                $table->string('nit', 20);
                $table->string('legal_name');
                $table->string('trade_name')->nullable();
                $table->string('address')->nullable();
                $table->string('municipality')->nullable();
                $table->string('phone')->nullable();
                $table->unsignedSmallInteger('branch_code')->default(0)->comment('0 = casa matriz');
                $table->unsignedSmallInteger('pos_code')->default(0)->comment('Punto de venta registrado en el SIN');
                $table->unsignedTinyInteger('modality')->default(1)->comment('1 electrónica en línea, 2 computarizada en línea');
                $table->string('environment', 20)->default('test')->comment('test | production');
                $table->string('system_code')->nullable()->comment('Código de sistema asignado por el SIN');
                $table->string('driver', 30)->default('floogos')->comment('floogos | simulated');
                $table->string('provider_credential')->nullable()->comment('Referencia de la credencial siat en el proveedor');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['tenant_id', 'created_at']);
                $table->unique(['tenant_id', 'nit', 'branch_code', 'pos_code'], 'aero_inv_issuer_unique');
            });
        }

        if (!Schema::hasTable('aero_invoicing_cufds')) {
            Schema::create('aero_invoicing_cufds', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('issuer_id');
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('cuis')->nullable();
                $table->string('cufd');
                $table->string('control_code');
                $table->string('address')->nullable();
                $table->timestamp('valid_until');
                $table->timestamps();

                $table->index(['issuer_id', 'valid_until']);
                $table->index(['tenant_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('aero_invoicing_invoices')) {
            Schema::create('aero_invoicing_invoices', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('issuer_id');
                $table->string('reference')->nullable()->comment('Referencia externa; idempotencia por tenant');
                $table->string('status', 20)->default('draft')->comment('draft, pending, received, validated, rejected, voided, error');
                $table->string('buyer_nit', 30);
                $table->unsignedTinyInteger('buyer_doc_type')->default(5)->comment('1 CI, 2 CEX, 3 PAS, 4 OD, 5 NIT');
                $table->string('buyer_name');
                $table->string('buyer_email')->nullable();
                $table->json('items');
                $table->decimal('total', 14, 2)->default(0);
                $table->decimal('iva', 14, 2)->default(0);
                $table->string('number', 30)->nullable();
                $table->string('cuf', 64)->nullable()->index();
                $table->string('reception_code', 60)->nullable();
                $table->string('qr_url', 500)->nullable();
                $table->json('messages')->nullable();
                $table->text('error')->nullable();
                $table->longText('xml')->nullable();
                $table->timestamp('issued_at')->nullable();
                $table->timestamp('voided_at')->nullable();
                $table->unsignedSmallInteger('void_reason_code')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'created_at']);
                $table->index('status');
                $table->unique(['tenant_id', 'reference'], 'aero_inv_reference_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_invoicing_invoices');
        Schema::dropIfExists('aero_invoicing_cufds');
        Schema::dropIfExists('aero_invoicing_issuers');
    }
};
