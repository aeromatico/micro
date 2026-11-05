<?php

use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aero_tickets_categories')) {
            return;
        }

        $items = [
            'Cumpleaños', 'Graduaciones', 'Fiestas privadas', 'Reuniones empresariales', 'Talleres',
            'Seminarios', 'Conferencias', 'Eventos universitarios', 'Conciertos', 'Festivales',
            'Deportes', 'Teatro y espectáculos', 'Otro',
        ];

        foreach ($items as $i => $name) {
            \Aero\Tickets\Models\Category::firstOrCreate(
                ['slug' => \Str::slug($name)],
                ['name' => $name, 'sort_order' => ($i + 1) * 10, 'is_active' => true]
            );
        }
    }

    public function down(): void
    {
    }
};
