<?php namespace Aero\Hub\Classes;

use Aero\Hub\Models\HubEndpoint;
use Http;
use Str;

/**
 * Convierte el spec OpenAPI de YepAPI (snapshot en resources/yepapi_openapi.json,
 * o re-descargado con --fetch) en filas de `HubEndpoint`. Es un upsert por
 * `code` (el operationId original) que SOLO toca columnas de metadata
 * (path/método/summary/description/schema/reference_cost_usd) — nunca
 * `credit_type_id`/`credit_cost`/`overage_credit_cost`/`is_active`/`count_path`,
 * que son decisiones del superadmin tomadas desde el panel.
 *
 * También construye, a partir de las filas ya guardadas, los grupos de scopes
 * y de endpoints que `Plugin::boot()` publica hacia Aero.Api — así ninguno de
 * los dos queda como un array literal de 155 entradas dentro de Plugin.php.
 */
class CatalogSync
{
    protected const SOURCE_URL = 'https://docs.yepapi.com/openapi.json';
    protected const SNAPSHOT_PATH = __DIR__ . '/../resources/yepapi_openapi.json';

    /** Categorías (tags del spec) que forman la división "Modelos IA"; el resto cae en "apis". */
    protected const AI_CATEGORIES = ['AI', 'AI Media'];

    /**
     * @return array{created:int, updated:int, total:int}
     */
    public static function run(bool $fetch = false): array
    {
        $spec = static::loadSpec($fetch);
        $created = 0;
        $updated = 0;

        foreach ((array) ($spec['paths'] ?? []) as $path => $methods) {
            foreach ($methods as $method => $operation) {
                if (!is_array($operation) || empty($operation['operationId'])) {
                    continue;
                }

                $isNew = !HubEndpoint::where('code', $operation['operationId'])->exists();
                static::upsertEndpoint($path, strtoupper($method), $operation);

                $isNew ? $created++ : $updated++;
            }
        }

        return ['created' => $created, 'updated' => $updated, 'total' => $created + $updated];
    }

    protected static function loadSpec(bool $fetch): array
    {
        if ($fetch) {
            $response = Http::timeout(30)->get(static::SOURCE_URL);

            if ($response->successful()) {
                file_put_contents(static::SNAPSHOT_PATH, $response->body());
            }
        }

        return json_decode((string) file_get_contents(static::SNAPSHOT_PATH), true) ?: [];
    }

    protected static function upsertEndpoint(string $path, string $method, array $operation): void
    {
        $category = $operation['tags'][0] ?? 'Other';
        $description = (string) ($operation['description'] ?? '');

        $endpoint = HubEndpoint::firstOrNew(['code' => $operation['operationId']]);

        $endpoint->fill([
            'category'           => $category,
            'division'           => in_array($category, static::AI_CATEGORIES, true) ? 'ai_models' : 'apis',
            'path'               => $path,
            'method'             => $method,
            'summary'            => $operation['summary'] ?? null,
            'description'        => $description ?: null,
            'request_schema'     => static::requestSchema($operation),
            'pricing_type'       => static::inferPricingType($path, $description),
            'reference_cost_usd' => static::parseReferenceCost($description),
            'is_streaming'       => static::hasStreamOption($operation),
            'is_async'           => $category === 'AI Media',
            'last_synced_at'     => now(),
        ]);

        // Solo al crearla: un borrador razonable que el superadmin revisa antes
        // de activar, nunca pisa un ajuste ya guardado en una fila existente.
        // credit_cost NO se asigna acá: HubEndpoint::beforeSave() lo calcula
        // solo a partir de reference_cost_usd + el margen global de
        // Aero\Hub\Models\Settings apenas se asigna credit_type_id abajo.
        if (!$endpoint->exists) {
            $endpoint->credit_type_id = static::defaultCreditTypeId();
            $endpoint->is_active = false;
        }

        $endpoint->save();
    }

    protected static function requestSchema(array $operation): ?string
    {
        $schema = $operation['requestBody']['content']['application/json']['schema'] ?? null;

        return $schema ? json_encode($schema) : null;
    }

    protected static function hasStreamOption(array $operation): bool
    {
        $properties = $operation['requestBody']['content']['application/json']['schema']['properties'] ?? [];

        return array_key_exists('stream', $properties);
    }

    protected static function inferPricingType(string $path, string $description): string
    {
        if (str_starts_with($path, '/v1/media/')) {
            return 'async';
        }

        if (preg_match('/per\s+\d+\s+(results?|pages?)/i', $description)) {
            return 'per_page';
        }

        if (preg_match('/per\s+100\b/i', $description)) {
            return 'per_volume';
        }

        return 'fixed';
    }

    /** Texto libre tipo "Cost: $0.01/call." o "Cost: ~$1.50 est.." — solo referencia, nunca el precio real cobrado. */
    protected static function parseReferenceCost(string $description): ?float
    {
        if (!preg_match('/Cost:\s*~?\$([0-9]+(?:\.[0-9]+)?)/i', $description, $m)) {
            return null;
        }

        return (float) $m[1];
    }

    /**
     * No asume nombres de color fijos ("azul"/"rojo"): cada instalación
     * define los suyos (esta, por ejemplo, usa bronce/plata/oro). El borrador
     * usa el tipo activo más barato — el superadmin lo revisa y ajusta desde
     * el panel antes de activar cada endpoint.
     */
    protected static function cheapestActiveCreditType(): ?\Aero\Credits\Models\CreditType
    {
        if (!class_exists(\Aero\Credits\Models\CreditType::class)) {
            return null;
        }

        return \Aero\Credits\Models\CreditType::active()->orderBy('usd_value')->first();
    }

    protected static function defaultCreditTypeId(): ?int
    {
        return static::cheapestActiveCreditType()?->id;
    }

    // -------------------------------------------------------------------
    // Publicación hacia Aero.Api (aero.api.registerScopes / registerEndpoints)
    // -------------------------------------------------------------------

    /**
     * Dos grupos separados ("Hub — Modelos IA" / "Hub — APIs") para que el
     * checkbox de permisos de una ApiKey muestre la misma división que pidió
     * el usuario, con un scope por categoría (hub.ai, hub.seo, hub.youtube...).
     */
    public static function scopeGroups(): array
    {
        $categories = HubEndpoint::query()->select('category', 'division')->distinct()->get();

        $groups = [
            'hub_ai_models' => ['label' => trans('aero.hub::lang.menu.ai_models'), 'scopes' => []],
            'hub_apis'      => ['label' => trans('aero.hub::lang.menu.apis'), 'scopes' => []],
        ];

        foreach ($categories as $row) {
            $scope = static::scopeFor($row->category);
            $key = $row->division === 'ai_models' ? 'hub_ai_models' : 'hub_apis';
            $groups[$key]['scopes'][$scope] = "YepAPI — {$row->category}";
        }

        return $groups;
    }

    public static function scopeFor(string $category): string
    {
        return 'hub.' . Str::slug($category, '_');
    }

    // -------------------------------------------------------------------
    // Página pública /hub (themes/master/pages/hub.htm, componente HubCatalog)
    // -------------------------------------------------------------------

    /**
     * Catálogo activo agrupado por división → categoría, con el hint de
     * costo en créditos de cada endpoint (mismo cálculo que
     * EndpointRegistry::withCreditHints(), sin pasar por Aero.Api porque acá
     * el consumidor es la página pública, no el explorador de API).
     *
     * @return array{ai_models: array, apis: array} cada una con 'label' y 'categories' (['Nombre' => ['label','endpoints' => [...]]])
     */
    public static function publicCatalog(): array
    {
        $out = [
            'ai_models' => ['label' => trans('aero.hub::lang.menu.ai_models'), 'categories' => []],
            'apis'      => ['label' => trans('aero.hub::lang.menu.apis'), 'categories' => []],
        ];

        $endpoints = HubEndpoint::where('is_active', true)->orderBy('category')->orderBy('path')->get();

        foreach ($endpoints as $endpoint) {
            $division = $endpoint->division === 'ai_models' ? 'ai_models' : 'apis';

            $out[$division]['categories'][$endpoint->category]['label'] = $endpoint->category;
            $out[$division]['categories'][$endpoint->category]['endpoints'][] = [
                'method'      => $endpoint->method,
                'path'        => '/hub' . $endpoint->path,
                'summary'     => $endpoint->summary,
                'description' => $endpoint->description,
                'credit'      => static::creditHintFor($endpoint),
            ];
        }

        foreach ($out as &$division) {
            $division['categories'] = array_values($division['categories']);
        }

        return $out;
    }

    protected static function creditHintFor(HubEndpoint $endpoint): ?array
    {
        if (!class_exists(\Aero\Credits\Classes\Credits::class)) {
            return null;
        }

        try {
            $cost = \Aero\Credits\Classes\Credits::cost($endpoint->actionCode());
        }
        catch (\Throwable $e) {
            return null;
        }

        if (!$cost['type'] || $cost['amount'] < 1) {
            return null;
        }

        return [
            'amount' => $cost['amount'],
            'color'  => $cost['type']->color,
            'label'  => $cost['amount'] . ' ' . ($cost['amount'] === 1 ? 'crédito' : 'créditos') . ' de ' . $cost['type']->label,
        ];
    }

    /**
     * Conteos para la copia estática de /hub y la sección de docs.htm: total
     * activo, por división, y cantidad de categorías distintas de cada una.
     */
    public static function publicStats(): array
    {
        $active = HubEndpoint::where('is_active', true)->get(['division', 'category']);

        return [
            'total'          => $active->count(),
            'ai_models'      => $active->where('division', 'ai_models')->count(),
            'apis'           => $active->where('division', 'apis')->count(),
            'categories'     => $active->pluck('category')->unique()->count(),
        ];
    }

    /** Una entrada por HubEndpoint activo para el explorador público /api. */
    public static function endpointGroups(): array
    {
        $endpoints = HubEndpoint::where('is_active', true)->orderBy('category')->orderBy('path')->get();

        if ($endpoints->isEmpty()) {
            return [];
        }

        $byDivision = [
            'hub_ai_models' => ['label' => trans('aero.hub::lang.menu.ai_models'), 'endpoints' => []],
            'hub_apis'      => ['label' => trans('aero.hub::lang.menu.apis'), 'endpoints' => []],
        ];

        foreach ($endpoints as $endpoint) {
            $key = $endpoint->division === 'ai_models' ? 'hub_ai_models' : 'hub_apis';

            $byDivision[$key]['endpoints'][] = [
                'method'       => $endpoint->method,
                'path'         => '/hub' . $endpoint->path,
                'scope'        => static::scopeFor($endpoint->category),
                'summary'      => $endpoint->summary,
                'credit_action' => $endpoint->actionCode(),
            ];
        }

        return array_filter($byDivision, fn ($g) => !empty($g['endpoints']));
    }
}
