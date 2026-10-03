<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_shop_orders', function (Blueprint $table) {
            $table->string('kitchen_status', 20)->nullable(); // new | preparing | ready | delivered (solo restaurante)
            $table->timestamp('kitchen_updated_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->index(['tenant_id', 'kitchen_status']);
        });
    }

    public function down(): void
    {
        Schema::table('aero_shop_orders', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'kitchen_status']);
            $table->dropColumn(['kitchen_status', 'kitchen_updated_at', 'ready_at']);
        });
    }
};
