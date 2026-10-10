<?php namespace Aero\Services\Classes;

use Illuminate\Support\Facades\Cache;

/**
 * Íconos de Lucide como SVG en línea, a partir del NOMBRE guardado en el
 * servicio (Service.icon). El set vive en classes/lucide/icons.json (generado
 * del paquete `lucide`); así este sitio no carga la librería JS completa y el
 * otro extremo (boliviahost.com) la dibuja con su propio Lucide: solo se
 * guarda y se intercambia el nombre.
 */
class LucideIcon
{
    protected static ?array $set = null;

    /** SVG listo para pintar, o '' si el nombre no es válido o no existe en el set. */
    public static function svg(?string $name, string $class = 'w-4 h-4'): string
    {
        $name = strtolower(trim((string) $name));

        if (!preg_match('/^[a-z0-9-]{1,60}$/', $name)) {
            return '';
        }

        $inner = Cache::rememberForever('aero.services.lucide.v1.' . $name, function () use ($name) {
            return static::set()[$name] ?? '';
        });

        if ($inner === '') {
            return '';
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" class="' . e($class) . '" width="24" height="24" viewBox="0 0 24 24" '
            . 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . $inner . '</svg>';
    }

    public static function exists(?string $name): bool
    {
        $name = strtolower(trim((string) $name));

        return preg_match('/^[a-z0-9-]{1,60}$/', $name) === 1 && isset(static::set()[$name]);
    }

    protected static function set(): array
    {
        return static::$set ??= (json_decode((string) @file_get_contents(__DIR__ . '/lucide/icons.json'), true) ?: []);
    }
}
