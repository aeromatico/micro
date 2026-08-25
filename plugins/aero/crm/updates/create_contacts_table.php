<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_crm_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('aero_sites_tenants')->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('aero_crm_companies')->nullOnDelete();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('source')->nullable();
            $table->unsignedInteger('owner_id')->nullable();
            $table->foreign('owner_id')->references('id')->on('backend_users')->nullOnDelete();
            $table->foreignId('shop_customer_id')->nullable()->constrained('aero_shop_customers')->nullOnDelete();
            $table->unsignedBigInteger('hello_contact_id')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_crm_contacts');
    }
};
