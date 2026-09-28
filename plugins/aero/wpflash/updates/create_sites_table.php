<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_wpflash_sites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->unique();
            $table->unsignedBigInteger('wp_site_id')->nullable();
            $table->string('wp_admin_url')->nullable();
            $table->string('primary_domain')->nullable();
            $table->string('status')->default('provisioning');
            $table->string('admin_username')->nullable();
            $table->text('admin_password_encrypted')->nullable();
            $table->unsignedBigInteger('connector_id')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->foreign('tenant_id')->references('id')->on('aero_sites_tenants')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_wpflash_sites');
    }
};
