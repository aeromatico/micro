<?php

use Illuminate\Database\Schema\Blueprint;
use October\Rain\Support\Facades\Schema;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_services_collections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->string('summary', 300)->nullable();
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('aero_services_collection_service', function (Blueprint $table) {
            $table->foreignId('collection_id')->constrained('aero_services_collections')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('aero_services_services')->cascadeOnDelete();
            $table->primary(['collection_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_services_collection_service');
        Schema::dropIfExists('aero_services_collections');
    }
};
