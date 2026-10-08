<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_shop_settings', function (Blueprint $table) {
            $table->json('restaurant_config')->nullable();
        });

        Schema::create('aero_shop_modifier_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('aero_sites_tenants')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('aero_shop_products')->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedSmallInteger('min_select')->default(0);
            $table->unsignedSmallInteger('max_select')->default(1);
            $table->json('choices')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_id']);
        });

        Schema::table('aero_shop_orders', function (Blueprint $table) {
            $table->string('order_type', 20)->nullable();
            $table->string('table_label', 30)->nullable();
            $table->timestamp('scheduled_for')->nullable();
        });

        Schema::table('aero_shop_order_items', function (Blueprint $table) {
            $table->json('modifiers')->nullable();
            $table->string('note', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('aero_shop_order_items', function (Blueprint $table) {
            $table->dropColumn(['modifiers', 'note']);
        });
        Schema::table('aero_shop_orders', function (Blueprint $table) {
            $table->dropColumn(['order_type', 'table_label', 'scheduled_for']);
        });
        Schema::dropIfExists('aero_shop_modifier_groups');
        Schema::table('aero_shop_settings', function (Blueprint $table) {
            $table->dropColumn('restaurant_config');
        });
    }
};
