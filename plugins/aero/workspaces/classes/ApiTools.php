<?php namespace Aero\Workspaces\Classes;

/**
 * Herramienta de solo lectura para el especialista en integraciones: el catálogo
 * PÚBLICO de la API de la plataforma (el mismo que se ve en /api). No toca datos
 * de ningún tenant ni credenciales; Aero.Api es dependencia blanda.
 */
class ApiTools
{
    public const CATEGORY = 'api_docs';

    public static function tools(): array
    {
        return [
            'api_catalog' => [
                'description' => 'Catálogo de la API REST de la plataforma (áreas, endpoints, scopes requeridos, parámetros y ejemplos de cuerpo). Sin `group` devuelve las áreas con sus endpoints resumidos; con `group` el detalle completo de esa área. `q` filtra por texto.',
                'category'    => static::CATEGORY,
                'parameters'  => ['type' => 'object', 'properties' => [
                    'group' => ['type' => 'string', 'description' => 'Clave del área (p. ej. hello, pay, shop).'],
                    'q'     => ['type' => 'string', 'description' => 'Busca en ruta, resumen y scope.'],
                ]],
                'handler'     => [static::class, 'catalog'],
            ],
        ];
    }

    public static function catalog(array $args, int $tenantId): array
    {
        if (!class_exists(\Aero\Api\Classes\EndpointRegistry::class)) {
            return ['error' => 'El catálogo de la API no está disponible en esta instalación.'];
        }

        $groups = \Aero\Api\Classes\EndpointRegistry::groups();
        $only = trim((string) ($args['group'] ?? ''));
        $q = mb_strtolower(trim((string) ($args['q'] ?? '')));

        if ($only !== '' && !isset($groups[$only])) {
            return ['error' => 'No existe esa área.', 'groups' => array_keys($groups)];
        }

        $out = [];

        foreach ($groups as $key => $group) {
            if ($only !== '' && $key !== $only) {
                continue;
            }

            $endpoints = [];

            foreach ((array) ($group['endpoints'] ?? []) as $e) {
                $hay = mb_strtolower(($e['method'] ?? '') . ' ' . ($e['path'] ?? '') . ' ' . ($e['summary'] ?? '') . ' ' . ($e['scope'] ?? ''));

                if ($q !== '' && !str_contains($hay, $q)) {
                    continue;
                }

                $endpoints[] = $only !== ''
                    ? array_intersect_key($e, array_flip(['method', 'path', 'scope', 'summary', 'path_params', 'query', 'body_example']))
                    : array_intersect_key($e, array_flip(['method', 'path', 'scope', 'summary']));
            }

            if ($endpoints) {
                $out[] = ['group' => $key, 'label' => $group['label'] ?? $key, 'endpoints' => $endpoints];
            }
        }

        return ['groups' => $out, 'auth' => 'Bearer <API key> creada en Aero.Api con los scopes indicados; todo queda aislado por tenant.'];
    }
}
