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
        if (!$endpoint->exists) {
            $endpoint->credit_type_id = static::defaultCreditTypeId();
            $endpoint->credit_cost = static::draftCreditCost($endpoint->reference_cost_usd);
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

    protected static function draftCreditCost(?float $referenceCostUsd): int
    {
        if (!$referenceCostUsd) {
            return 0;
        }

        $type = static::cheapestActiveCreditType();

        if (!$type || (float) $type->usd_value <= 0) {
            return 0;
        }

        return (int) max(1, ceil($referenceCostUsd / (float) $type->usd_value));
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
