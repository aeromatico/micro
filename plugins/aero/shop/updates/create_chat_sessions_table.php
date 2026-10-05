<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Estado de compra por chat: la última lista que se le mostró a un cliente
 * (categorías, productos, variantes) y su carrito. Se identifica por tenant +
 * clave de contacto (teléfono). Caduca solo (expires_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('aero_shop_chat_sessions')) {
            return;
        }

        Schema::create('aero_shop_chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('contact_key', 80);
            $table->text('last_list')->nullable();
            $table->text('cart')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'contact_key']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_shop_chat_sessions');
    }
};
