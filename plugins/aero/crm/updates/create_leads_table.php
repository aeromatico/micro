<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_crm_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('aero_sites_tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('company_name')->nullable();
            $table->string('source')->nullable();
            $table->string('status')->default('new')->comment('new, contacted, qualified, disqualified');
            $table->unsignedInteger('owner_id')->nullable();
            $table->foreign('owner_id')->references('id')->on('backend_users')->nullOnDelete();
            $table->foreignId('converted_contact_id')->nullable()->constrained('aero_crm_contacts')->nullOnDelete();
            $table->foreignId('converted_deal_id')->nullable()->constrained('aero_crm_deals')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_crm_leads');
    }
};
