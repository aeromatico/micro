<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_chatbots_bots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('aero_sites_tenants')->cascadeOnDelete();
            $table->foreignId('account_id')->unique()->constrained('aero_hello_accounts')->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->text('fallback_message')->nullable();
            $table->unsignedSmallInteger('handoff_minutes')->default(30);
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_chatbots_bots');
    }
};
