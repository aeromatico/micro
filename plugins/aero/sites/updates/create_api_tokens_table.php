<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_sites_api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('aero_sites_tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('token', 64)->unique()->comment('SHA256 hash del token');
            $table->json('abilities')->nullable()->comment('Array de strings: pages:read, contact:submit, etc.');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id']);
            $table->index('token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_sites_api_tokens');
    }
};
