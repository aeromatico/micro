<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use October\Rain\Database\Updates\Migration;

/**
 * Íconos Lucide iniciales de los servicios publicados. Solo rellena los que
 * están vacíos: lo que se edite después desde el backend no se pisa.
 */
return new class extends Migration
{
    protected array $icons = [
        'abogados-y-estudios-juridicos' => 'scale',
        'barberias-y-salones-de-belleza' => 'scissors',
        'bolivia-pay' => 'qr-code',
        'chatbots' => 'bot',
        'colegios-e-institutos' => 'graduation-cap',
        'conector-google-sheets' => 'sheet',
        'consultorios' => 'stethoscope',
        'crm' => 'users',
        'eventos-y-salones' => 'party-popper',
        'farmacias' => 'pill',
        'ferreterias-y-bazares' => 'hammer',
        'flotas-y-transporte' => 'truck',
        'freelancers-y-consultores' => 'briefcase',
        'gimnasios' => 'dumbbell',
        'hello' => 'message-circle',
        'iglesias-y-congregaciones' => 'church',
        'inmobiliarias' => 'building-2',
        'mascotas' => 'paw-print',
        'omnichat' => 'messages-square',
        'ongs-y-fundaciones' => 'heart-handshake',
        'panaderias-y-reposteria' => 'croissant',
        'punto-de-venta-pos' => 'store',
        'radioemisoras' => 'radio',
        'restaurantes' => 'utensils',
        'sitios' => 'globe',
        'talleres-mecanicos' => 'wrench',
        'tickets' => 'ticket',
        'tiendas-de-ropa-y-moda' => 'shirt',
        'tiendas-por-whatsapp' => 'shopping-bag',
        'turismo' => 'plane',
        'wordpress-flash' => 'zap',
        'workflows' => 'workflow',
    ];

    public function up(): void
    {
        foreach ($this->icons as $slug => $icon) {
            DB::table('aero_services_services')
                ->where('slug', $slug)
                ->where(fn ($q) => $q->whereNull('icon')->orWhere('icon', ''))
                ->update(['icon' => $icon]);
        }

        Cache::forget('aero.services.public_menu');
    }

    public function down(): void
    {
        // Datos editables: no se deshace la semilla.
    }
};
