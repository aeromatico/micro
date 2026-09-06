<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_sites_tenant_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('aero_sites_tenants')->cascadeOnDelete();
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('role', 30)->default('admin');
            $table->string('token', 64)->unique();
            $table->unsignedInteger('invited_by')->nullable();
            $table->unsignedInteger('backend_user_id')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_sites_tenant_invites');
    }
};
