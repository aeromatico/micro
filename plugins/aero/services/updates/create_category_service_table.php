<?php

use Illuminate\Database\Schema\Blueprint;
use October\Rain\Support\Facades\Schema;
use October\Rain\Database\Updates\Migration;

/** Un servicio puede tener varias categorías. category_id queda como columna heredada (ya no se usa). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_services_category_service', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained('aero_services_categories')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('aero_services_services')->cascadeOnDelete();
            $table->primary(['category_id', 'service_id']);
        });

        \DB::statement('INSERT INTO aero_services_category_service (category_id, service_id)
            SELECT category_id, id FROM aero_services_services WHERE category_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_services_category_service');
    }
};
