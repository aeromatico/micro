<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_crm_deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('aero_sites_tenants')->cascadeOnDelete();
            $table->foreignId('pipeline_id')->constrained('aero_crm_pipelines')->cascadeOnDelete();
            $table->foreignId('stage_id')->constrained('aero_crm_pipeline_stages')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('aero_crm_contacts')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('aero_crm_companies')->nullOnDelete();
            $table->string('title');
            $table->decimal('value', 14, 2)->nullable();
            $table->string('currency', 5)->nullable();
            $table->unsignedInteger('owner_id')->nullable();
            $table->foreign('owner_id')->references('id')->on('backend_users')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('aero_crm_teams')->nullOnDelete();
            $table->date('expected_close_date')->nullable();
            $table->string('status')->default('open')->comment('open, won, lost');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'stage_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_crm_deals');
    }
};
