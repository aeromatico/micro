<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Modo «Marketplace»: comerciantes (miembros del equipo del tenant) con su
 * cuenta de cobro y comisión; cada producto pertenece a un comerciante y cada
 * compra se divide en un pedido por comerciante.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_shop_sellers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('aero_sites_tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('backend_user_id');
            $table->string('display_name', 120);
            $table->unsignedBigInteger('bank_account_id')->nullable();
            $table->string('commission_type', 10)->default('percent');
            $table->decimal('commission_value', 12, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'backend_user_id']);
        });

        Schema::table('aero_shop_products', function (Blueprint $table) {
            $table->unsignedBigInteger('seller_id')->nullable()->index();
        });

        Schema::table('aero_shop_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('seller_id')->nullable()->index();
            $table->string('order_group', 40)->nullable()->index();
            $table->decimal('commission_amount', 14, 4)->default(0);
            $table->decimal('seller_net', 14, 4)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('aero_shop_orders', function (Blueprint $table) {
            $table->dropColumn(['seller_id', 'order_group', 'commission_amount', 'seller_net']);
        });
        Schema::table('aero_shop_products', function (Blueprint $table) {
            $table->dropColumn('seller_id');
        });
        Schema::dropIfExists('aero_shop_sellers');
    }
};
