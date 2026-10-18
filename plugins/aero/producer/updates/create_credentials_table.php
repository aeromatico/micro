<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('aero_producer_credentials')) {
            return;
        }

        Schema::create('aero_producer_credentials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable(); // NULL = la de la plataforma (los tenants la usan pagando en créditos)
            $table->string('provider', 20)->default('elevenlabs');
            $table->string('label');
            $table->text('api_key_enc')->nullable();              // cifrada (Crypt)
            $table->boolean('is_active')->default(true);
            $table->string('last_status', 20)->nullable();
            $table->string('last_message', 500)->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_producer_credentials');
    }
};
