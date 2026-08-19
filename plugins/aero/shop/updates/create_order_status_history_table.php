<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_shop_order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('aero_shop_orders')->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->unsignedInteger('changed_by_backend_user_id')->nullable();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_shop_order_status_history');
    }
};
