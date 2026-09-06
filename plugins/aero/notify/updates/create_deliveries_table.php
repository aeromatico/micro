<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_notify_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('aero_notify_events')->cascadeOnDelete();
            $table->foreignId('rule_id')->nullable()->constrained('aero_notify_rules')->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('aero_notify_templates')->nullOnDelete();
            $table->unsignedInteger('tenant_id')->default(0);
            $table->string('audience', 40);
            $table->string('channel', 30);
            $table->string('address')->nullable();
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('external_id')->nullable();
            $table->text('error')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['event_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_notify_deliveries');
    }
};
