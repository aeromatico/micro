<?php

use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_sites_tenant_users', function ($table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('aero_sites_tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->comment('RainLab\\User\\Models\\User');
            $table->string('role', 30)->default('user')->comment('admin|moderator|user');
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id']);
            $table->index('user_id');
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_sites_tenant_users');
    }
};
