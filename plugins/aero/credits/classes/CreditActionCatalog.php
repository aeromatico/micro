<?php namespace Aero\Credits\Classes;

/**
 * Catálogo de acciones facturables sembradas de fábrica (mismo espíritu que
 * Aero\Notify\Classes\EventCatalog: una lista estática que consume una
 * migración seed, no un registro dinámico por evento — nada fuera de este
 * plugin necesita declarar acciones nuevas hoy, y agregar una es una fila
 * más acá + un `credits.charge()` en el punto de integración).
 *
 * `type` referencia el `code` de un CreditType sembrado por
 * updates/seed_credit_types_and_actions.php (azul/rojo).
 */
class CreditActionCatalog
{
    public static function defaults(): array
    {
        return [
            [
                'code'         => 'aifields.complete',
                'label'        => 'IA de formularios — generar/mejorar/traducir texto',
                'plugin'       => 'Aero.AiFields',
                'type'         => 'azul',
                'default_cost' => 1,
            ],
            [
                'code'         => 'aifields.code',
                'label'        => 'IA de formularios — modo Developer (código/diseño)',
                'plugin'       => 'Aero.AiFields',
                'type'         => 'azul',
                'default_cost' => 2,
            ],
            [
                'code'         => 'hello.voice_call',
                'label'        => 'Llamada de voz IA por WhatsApp',
                'plugin'       => 'Aero.Hello',
                'type'         => 'rojo',
                'default_cost' => 5,
            ],
        ];
    }
}
