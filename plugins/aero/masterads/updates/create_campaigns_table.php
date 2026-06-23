<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_masterads_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meta_account_id')
                ->constrained('aero_masterads_meta_accounts')
                ->cascadeOnDelete();
            $table->string('meta_id', 64)->unique();
            $table->string('name', 255);
            $table->string('objective', 64)->nullable();
            $table->enum('status', ['ACTIVE', 'PAUSED', 'ARCHIVED', 'DELETED'])->default('PAUSED');
            $table->decimal('daily_budget', 12, 4)->nullable();
            $table->decimal('lifetime_budget', 12, 4)->nullable();
            $table->dateTime('start_time')->nullable();
            $table->dateTime('stop_time')->nullable();
            $table->timestamps();

            $table->index(['meta_account_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_masterads_campaigns');
    }
};
