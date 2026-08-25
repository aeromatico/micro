<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_crm_collection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('aero_sites_tenants')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('aero_crm_contacts')->cascadeOnDelete();
            $table->foreignId('contact_list_id')->nullable()->constrained('aero_crm_contact_lists')->nullOnDelete();
            $table->unsignedInteger('owner_id')->nullable();
            $table->foreign('owner_id')->references('id')->on('backend_users')->nullOnDelete();

            $table->string('concept');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 8)->default('BOB');
            $table->date('due_date');
            $table->string('status', 20)->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('last_reminder_at')->nullable();
            $table->unsignedInteger('reminder_count')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_crm_collection_items');
    }
};
