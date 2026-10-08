<?php

use Illuminate\Database\Schema\Blueprint;
use October\Rain\Support\Facades\Schema;
use October\Rain\Database\Updates\Migration;

/** Guías interactivas (Aero.Docs) que se muestran dentro de la página pública de un servicio. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('aero_services_service_guide')) {
            return;
        }

        Schema::create('aero_services_service_guide', function (Blueprint $table) {
            $table->foreignId('service_id')->constrained('aero_services_services')->cascadeOnDelete();
            $table->foreignId('guide_id')->constrained('aero_docs_guides')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->primary(['service_id', 'guide_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_services_service_guide');
    }
};
