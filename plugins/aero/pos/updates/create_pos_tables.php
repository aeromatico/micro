<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_pos_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('aero_sites_tenants')->cascadeOnDelete();
            $table->string('profile', 20)->default('restaurant');   // restaurant | retail | quick
            $table->boolean('feat_tables')->default(true);
            $table->boolean('feat_tabs')->default(true);            // cuentas abiertas
            $table->boolean('feat_kitchen')->default(true);
            $table->boolean('feat_tips')->default(true);
            $table->boolean('feat_barcode')->default(false);
            $table->boolean('feat_free_amount')->default(false);   // «Venta libre» de monto abierto
            $table->string('tip_presets', 60)->default('0,5,10');
            $table->unsignedSmallInteger('discount_limit_percent')->default(10); // sin PIN de supervisor
            $table->boolean('require_shift')->default(true);
            $table->unsignedSmallInteger('ticket_width')->default(80);           // mm: 58 | 80
            $table->text('ticket_header')->nullable();
            $table->text('ticket_footer')->nullable();
            $table->timestamps();
        });

        Schema::create('aero_pos_terminals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('aero_sites_tenants')->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('code', 30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('aero_pos_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('aero_sites_tenants')->cascadeOnDelete();
            $table->string('zone', 60)->nullable();
            $table->string('name', 30);
            $table->unsignedSmallInteger('seats')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('aero_pos_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('aero_sites_tenants')->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('label', 60);
            $table->string('kind', 15)->default('other');           // cash | qr | card | transfer | other
            $table->unsignedBigInteger('gateway_id')->nullable();    // aero_shop_payment_gateways (QR)
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('aero_pos_cashiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('aero_sites_tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');                   // backend_users.id
            $table->string('pin_hash')->nullable();
            $table->unsignedSmallInteger('discount_limit_percent')->nullable(); // null = el del tenant
            $table->boolean('is_supervisor')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id']);
        });

        Schema::create('aero_pos_device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('aero_sites_tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('terminal_id')->nullable();
            $table->string('name', 80)->nullable();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('aero_pos_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('aero_sites_tenants')->cascadeOnDelete();
            $table->foreignId('terminal_id')->constrained('aero_pos_terminals')->cascadeOnDelete();
            $table->unsignedBigInteger('opened_by_user_id');
            $table->unsignedBigInteger('closed_by_user_id')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->decimal('opening_cash', 14, 4)->default(0);
            $table->decimal('expected_cash', 14, 4)->nullable();
            $table->decimal('counted_cash', 14, 4)->nullable();
            $table->decimal('difference', 14, 4)->nullable();
            $table->string('status', 10)->default('open');            // open | closed
            $table->text('notes')->nullable();
            $table->json('summary')->nullable();                      // reporte Z congelado al cerrar
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
            $table->index(['terminal_id', 'status']);
        });

        Schema::create('aero_pos_cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('aero_sites_tenants')->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained('aero_pos_shifts')->cascadeOnDelete();
            $table->string('type', 3);                                // in | out
            $table->decimal('amount', 14, 4);
            $table->string('reason', 160);
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });

        Schema::create('aero_pos_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('aero_sites_tenants')->cascadeOnDelete();
            $table->foreignId('order_id')->unique()->constrained('aero_shop_orders')->cascadeOnDelete();
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->unsignedBigInteger('terminal_id')->nullable();
            $table->unsignedBigInteger('cashier_user_id')->nullable();
            $table->unsignedBigInteger('table_id')->nullable();
            $table->string('tab_state', 10)->default('open');         // open | closed
            $table->decimal('tip_total', 14, 4)->default(0);
            $table->string('discount_reason', 160)->nullable();
            $table->string('nit', 30)->nullable();
            $table->string('tax_name', 150)->nullable();
            $table->char('client_uuid', 36)->nullable();              // idempotencia de reintentos
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'client_uuid']);
            $table->index(['tenant_id', 'tab_state']);
            $table->index(['shift_id']);
        });

        Schema::create('aero_pos_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('aero_sites_tenants')->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained('aero_pos_sales')->cascadeOnDelete();
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->unsignedBigInteger('payment_method_id');
            $table->decimal('amount', 14, 4);                         // aplicado a la cuenta (sin vuelto)
            $table->decimal('tendered', 14, 4)->nullable();           // lo que entregó el cliente
            $table->decimal('change_given', 14, 4)->default(0);
            $table->string('reference', 120)->nullable();
            $table->string('status', 10)->default('completed');       // pending | completed | void
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->index(['shift_id', 'status']);
        });
    }

    public function down(): void
    {
        foreach (['payments', 'sales', 'cash_movements', 'shifts', 'device_tokens', 'cashiers', 'payment_methods', 'tables', 'terminals', 'settings'] as $t) {
            Schema::dropIfExists('aero_pos_' . $t);
        }
    }
};
