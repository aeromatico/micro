<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_sites_pages', function (Blueprint $table) {
            $table->boolean('show_in_menu')->default(false);
            $table->json('menu_positions')->nullable();
        });

        // Hasta hoy toda página publicada sale en la navegación y el pie: se conserva ese comportamiento.
        DB::table('aero_sites_pages')
            ->whereNull('deleted_at')
            ->update([
                'show_in_menu'   => true,
                'menu_positions' => json_encode(['navbar', 'sidebar', 'footer']),
            ]);
    }

    public function down(): void
    {
        Schema::table('aero_sites_pages', function (Blueprint $table) {
            $table->dropColumn(['show_in_menu', 'menu_positions']);
        });
    }
};
