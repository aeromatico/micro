<?php namespace Aero\Tracking\Classes\Feeds;

use Event;

/**
 * Drivers disponibles. Otros plugins pueden añadir los suyos devolviendo
 * clases FeedDriver desde el evento `aero.tracking.registerFeedDrivers`.
 */
class FeedRegistry
{
    /** @return array<string, FeedDriver> */
    public static function all(): array
    {
        $classes = [PedidosYaDriver::class];

        foreach (array_filter((array) Event::fire('aero.tracking.registerFeedDrivers')) as $extra) {
            $classes = array_merge($classes, (array) $extra);
        }

        $drivers = [];
        foreach ($classes as $class) {
            $driver = is_object($class) ? $class : new $class();
            $drivers[$driver->key()] = $driver;
        }

        return $drivers;
    }

    public static function get(string $key): ?FeedDriver
    {
        return self::all()[$key] ?? null;
    }

    /** @return array{0: FeedDriver, 1: array}|null */
    public static function detect(string $url): ?array
    {
        foreach (self::all() as $driver) {
            if ($found = $driver->match(trim($url))) {
                return [$driver, $found];
            }
        }

        return null;
    }

    public static function labels(): array
    {
        return array_map(fn (FeedDriver $d) => $d->label(), self::all());
    }
}
