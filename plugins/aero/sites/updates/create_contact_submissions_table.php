<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_sites_contact_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('aero_sites_tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 30)->nullable();
            $table->text('message');
            $table->json('metadata')->nullable()->comment('IP, user_agent, page_url');
            $table->enum('status', ['pending', 'sent', 'failed', 'partial'])->default('pending');
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_sites_contact_submissions');
    }
};
