<?php

use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * tenant_id / bank_account_id / qr_code_id sin FK a propósito: Aero.Shopify no
 * debe exigir que Sites o Pay existan para migrar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aero_shopify_stores')) {
            Schema::create('aero_shopify_stores', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('name');
                $table->string('shop_domain')->unique()->comment('xxx.myshopify.com');
                $table->text('access_token')->nullable()->comment('Cifrado (Crypt)');
                $table->text('client_secret')->nullable()->comment('Cifrado (Crypt); firma de webhooks');
                $table->unsignedBigInteger('bank_account_id')->nullable();
                $table->string('gateway_names')->default('QR Bolivia')->comment('Nombres del método de pago manual, separados por coma');
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_webhook_at')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('aero_shopify_orders')) {
            Schema::create('aero_shopify_orders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('store_id');
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('token', 64)->unique();
                $table->string('shopify_order_id', 40);
                $table->string('order_name', 40)->nullable()->comment('#1001');
                $table->string('customer_email')->nullable();
                $table->decimal('amount', 14, 2)->default(0);
                $table->string('currency', 3)->default('BOB');
                $table->unsignedBigInteger('qr_code_id')->nullable();
                $table->string('status', 20)->default('pending')->comment('pending, paid, cancelled, expired, error, skipped');
                $table->text('error')->nullable();
                $table->timestamp('paid_synced_at')->nullable();
                $table->timestamps();

                $table->unique(['store_id', 'shopify_order_id']);
                $table->index(['tenant_id', 'created_at']);
                $table->index('qr_code_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_shopify_orders');
        Schema::dropIfExists('aero_shopify_stores');
    }
};
