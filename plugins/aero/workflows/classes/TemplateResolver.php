<?php namespace Aero\Workflows\Classes;

/**
 * Plantillas `{{ nodes.http1.body.email }}` sin Twig ni eval: solo rutas con
 * puntos sobre el contexto. Si el valor es exactamente un placeholder se
 * devuelve con su tipo original; si va dentro de un texto, se castea a string.
 */
class TemplateResolver
{
    public static function resolve(mixed $value, array $ctx): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($v) => static::resolve($v, $ctx), $value);
        }

        if (!is_string($value) || !str_contains($value, '{{')) {
            return $value;
        }

        if (preg_match('/^\s*\{\{\s*([\w.\-]+)\s*\}\}\s*$/', $value, $m)) {
            return static::get($ctx, $m[1]);
        }

        return preg_replace_callback('/\{\{\s*([\w.\-]+)\s*\}\}/', function ($m) use ($ctx) {
            $v = static::get($ctx, $m[1]);

            return is_scalar($v) || $v === null ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE);
        }, $value);
    }

    public static function get(array $ctx, string $path): mixed
    {
        $current = $ctx;

        foreach (explode('.', $path) as $segment) {
            if (is_array($current) && array_key_exists($segment, $current)) {
                $current = $current[$segment];
            }
            else {
                return null;
            }
        }

        return $current;
    }
}
