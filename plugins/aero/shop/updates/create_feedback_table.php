<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/** Opiniones de los clientes sobre su pedido: internas, públicas o públicas con aprobación según la configuración. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_shop_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('aero_sites_tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('order_id');
            $table->unsignedTinyInteger('rating');                    // 1 a 5 estrellas
            $table->text('comment')->nullable();
            $table->string('author_name', 60)->nullable();
            $table->string('status', 12)->default('internal');        // internal | pending | approved | hidden
            $table->timestamps();
            $table->unique('order_id');
            $table->index(['tenant_id', 'status', 'created_at'], 'aero_shop_feedback_list');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_shop_feedback');
    }
};
