<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_masterads_ads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_set_id')
                ->constrained('aero_masterads_ad_sets')
                ->cascadeOnDelete();
            $table->string('meta_id', 64)->unique();
            $table->string('name', 255);
            $table->enum('status', ['ACTIVE', 'PAUSED', 'ARCHIVED', 'DELETED'])->default('PAUSED');
            $table->json('creative')->nullable();
            $table->enum('format', ['image', 'video', 'carousel', 'collection'])->default('image');
            $table->timestamps();

            $table->index(['ad_set_id', 'status']);
            $table->index('format');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_masterads_ads');
    }
};
