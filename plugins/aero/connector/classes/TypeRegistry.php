<?php namespace Aero\Connector\Classes;

use Event;
use Aero\Connector\Contracts\ConnectorDriver;

/**
 * Catálogo de tipos de conector disponibles. Cualquier plugin (incluido este
 * mismo, para sus tipos de fábrica) declara los suyos por evento:
 *
 *     Event::listen('aero.connector.registerTypes', function () {
 *         return [
 *             'mi_tipo' => [
 *                 'label'    => 'Mi API',
 *                 'category' => 'http', // http | ai | social
 *                 'driver'   => \Vendor\Plugin\Drivers\MiDriver::class,
 *                 'auth'     => 'bearer', // none|bearer|basic|api_key_header|api_key_query
 *                 'fields'   => [ ... esquema de campos no sensibles para config ... ],
 *                 'secret_fields' => [ ... esquema de campos sensibles para credentials ... ],
 *             ],
 *         ];
 *     });
 */
class TypeRegistry
{
    protected static ?array $types = null;

    public static function all(): array
    {
        if (static::$types !== null) {
            return static::$types;
        }

        $types = [];

        foreach ((array) Event::fire('aero.connector.registerTypes') as $result) {
            if (is_array($result)) {
                $types = array_merge($types, $result);
            }
        }

        return static::$types = $types;
    }

    public static function find(string $code): ?array
    {
        return static::all()[$code] ?? null;
    }

    public static function options(): array
    {
        $options = [];

        foreach (static::all() as $code => $type) {
            $options[$code] = $type['label'] ?? $code;
        }

        return $options;
    }

    public static function driverFor(string $code): ?ConnectorDriver
    {
        $type = static::find($code);

        if (!$type || empty($type['driver']) || !class_exists($type['driver'])) {
            return null;
        }

        return app($type['driver']);
    }

    public static function flush(): void
    {
        static::$types = null;
    }
}
