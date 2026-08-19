<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_shop_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('aero_sites_tenants')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('aero_shop_customers')->cascadeOnDelete();
            $table->string('order_number');
            $table->string('status')->default('pending')->comment('pending|awaiting_payment|paid|fulfilled|cancelled|refunded');
            $table->foreignId('currency_id')->constrained('aero_shop_currencies');
            $table->decimal('exchange_rate_snapshot', 18, 6)->default(1);
            $table->decimal('subtotal', 14, 4)->default(0);
            $table->decimal('discount_total', 14, 4)->default(0);
            $table->decimal('shipping_total', 14, 4)->default(0);
            $table->decimal('tax_total', 14, 4)->default(0);
            $table->decimal('grand_total', 14, 4)->default(0);
            $table->foreignId('payment_gateway_id')->nullable()->constrained('aero_shop_payment_gateways')->nullOnDelete();
            $table->string('payment_reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedInteger('paid_confirmed_by_backend_user_id')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->foreignId('shipping_address_id')->nullable()->constrained('aero_shop_addresses')->nullOnDelete();
            $table->foreignId('billing_address_id')->nullable()->constrained('aero_shop_addresses')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->text('customer_notes')->nullable();
            $table->boolean('requires_shipping')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'order_number']);
            $table->index(['tenant_id', 'status']);
            $table->index(['customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_shop_orders');
    }
};
