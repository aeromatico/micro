<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Prerrequisitos de aero/pos (venta presencial) en la tienda, todo aditivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_shop_orders', function (Blueprint $table) {
            $table->string('source', 20)->default('web');   // web | pos | api | whatsapp | chat
            $table->unsignedBigInteger('cashier_backend_user_id')->nullable();
            $table->index(['tenant_id', 'source']);
        });

        Schema::table('aero_shop_order_items', function (Blueprint $table) {
            $table->unsignedSmallInteger('round')->default(1);  // ronda de comanda (cuentas abiertas)
            $table->timestamp('fired_at')->nullable();           // cuándo se envió a cocina
        });

        Schema::table('aero_shop_products', function (Blueprint $table) {
            $table->string('barcode', 64)->nullable();
            $table->boolean('is_internal')->default(false);       // productos de sistema (ej. «Venta libre»)
            $table->index(['tenant_id', 'barcode']);
        });

        Schema::table('aero_shop_customers', function (Blueprint $table) {
            $table->string('tax_id', 30)->nullable();             // NIT / CI
            $table->string('tax_name', 150)->nullable();          // razón social
            $table->index(['tenant_id', 'tax_id']);
        });

        // Las órdenes anteriores se infieren de su historial: la primera fila guardaba el origen en «note».
        foreach (['whatsapp', 'api', 'chat'] as $source) {
            Db::table('aero_shop_orders')->whereIn('id', function ($q) use ($source) {
                $q->select('order_id')->from('aero_shop_order_status_history')->whereNull('from_status')->where('note', $source);
            })->update(['source' => $source]);
        }
    }

    public function down(): void
    {
        Schema::table('aero_shop_customers', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'tax_id']);
            $table->dropColumn(['tax_id', 'tax_name']);
        });
        Schema::table('aero_shop_products', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'barcode']);
            $table->dropColumn(['barcode', 'is_internal']);
        });
        Schema::table('aero_shop_order_items', function (Blueprint $table) {
            $table->dropColumn(['round', 'fired_at']);
        });
        Schema::table('aero_shop_orders', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'source']);
            $table->dropColumn(['source', 'cashier_backend_user_id']);
        });
    }
};
