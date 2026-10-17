<?php

use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('aero_rgpd_consents')) {
            return;
        }

        Schema::create('aero_rgpd_consents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('consent_id', 36);          // id anónimo del visitante (cookie)
            $table->boolean('analytics')->default(false);
            $table->boolean('marketing')->default(false);
            $table->unsignedInteger('config_version')->default(1);
            $table->string('ip_hash', 64)->nullable(); // HMAC, nunca la IP en claro
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index('consent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_rgpd_consents');
    }
};
