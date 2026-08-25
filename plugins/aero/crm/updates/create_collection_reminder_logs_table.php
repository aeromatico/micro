<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_crm_collection_reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_item_id')->constrained('aero_crm_collection_items')->cascadeOnDelete();
            $table->foreignId('collection_reminder_rule_id')->constrained('aero_crm_collection_reminder_rules')->cascadeOnDelete();
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['collection_item_id', 'collection_reminder_rule_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_crm_collection_reminder_logs');
    }
};
