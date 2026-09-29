<?php

use Illuminate\Database\Schema\Blueprint;
use October\Rain\Support\Facades\Schema;
use October\Rain\Database\Updates\Migration;

/**
 * Jerarquía: Colección → Categorías → Servicios (ambas relaciones múltiples).
 * Reemplaza el vínculo directo colección↔servicio: cada servicio que estaba en una colección pasa a
 * colgar de una categoría de esa colección (la suya; si no tenía, se crea una con el nombre de la colección).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_services_collection_category', function (Blueprint $table) {
            $table->foreignId('collection_id')->constrained('aero_services_collections')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('aero_services_categories')->cascadeOnDelete();
            $table->primary(['collection_id', 'category_id']);
        });

        if (!Schema::hasTable('aero_services_collection_service')) {
            return;
        }

        $collections = \DB::table('aero_services_collections')->get();

        foreach ($collections as $collection) {
            $serviceIds = \DB::table('aero_services_collection_service')->where('collection_id', $collection->id)->pluck('service_id');
            $categoryIds = [];

            foreach ($serviceIds as $serviceId) {
                $own = \DB::table('aero_services_category_service')->where('service_id', $serviceId)->pluck('category_id')->all();

                if (!$own) {
                    $cat = \DB::table('aero_services_categories')->where('slug', $collection->slug)->first();
                    $catId = $cat->id ?? \DB::table('aero_services_categories')->insertGetId([
                        'name' => $collection->name, 'slug' => $collection->slug, 'sort_order' => $collection->sort_order,
                        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    \DB::table('aero_services_category_service')->insertOrIgnore(['category_id' => $catId, 'service_id' => $serviceId]);
                    $own = [$catId];
                }

                $categoryIds = array_merge($categoryIds, $own);
            }

            foreach (array_unique($categoryIds) as $catId) {
                \DB::table('aero_services_collection_category')->insertOrIgnore(['collection_id' => $collection->id, 'category_id' => $catId]);
            }
        }

        Schema::dropIfExists('aero_services_collection_service');
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_services_collection_category');
    }
};
