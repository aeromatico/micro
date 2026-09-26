<?php namespace Aero\Hub\Classes;

use Aero\Hub\Models\HubEndpoint;
use Str;

/**
 * Genera la documentación pública de cada HubEndpoint activo como un
 * `Aero\Docs\Models\Article`, sin depender de contenido externo: usamos los
 * MISMOS endpoints que YepAPI (mismo path relativo, mismos parámetros,
 * misma respuesta) — lo único que cambia es el dominio/prefijo (`/hub` en vez
 * del propio de YepAPI). Con esa garantía, todo el contenido sale de lo que
 * `CatalogSync` ya parseó del spec OpenAPI local (summary/description/
 * request_schema) — no hace falta scrapear https://docs.yepapi.com/.
 *
 * El árbol de categorías queda: Hub → (Modelos IA | APIs de datos) →
 * <categoría del spec, ej. "SEO"> → un artículo por endpoint. El artículo
 * generado es la única fuente de esa página (no se edita a mano): un
 * re-sync sobreescribe título/contenido sin pisar nada fuera de esas dos
 * columnas.
 *
 * Disparado por HubEndpoint::afterSave() (un endpoint a la vez, automático
 * al guardar desde el backend) y por el comando `hub:sync-docs` (todos,
 * para el primer poblado o si cambia el formato de esta clase).
 */
class DocsSync
{
    /** @return array{created:int, updated:int, unpublished:int} */
    public static function runAll(): array
    {
        if (!static::available()) {
            return ['created' => 0, 'updated' => 0, 'unpublished' => 0];
        }

        $stats = ['created' => 0, 'updated' => 0, 'unpublished' => 0];

        HubEndpoint::chunkById(200, function ($endpoints) use (&$stats) {
            foreach ($endpoints as $endpoint) {
                $result = static::syncOne($endpoint);
                $stats[$result]++;
            }
        });

        return $stats;
    }

    /**
     * @return string 'created'|'updated'|'unpublished' — qué pasó con el artículo.
     */
    public static function syncOne(HubEndpoint $endpoint): string
    {
        if (!static::available()) {
            return 'unpublished';
        }

        if (!$endpoint->is_active) {
            return static::unpublish($endpoint);
        }

        $slug = static::articleSlug($endpoint);
        $article = \Aero\Docs\Models\Article::firstOrNew(['tenant_id' => null, 'slug' => $slug]);
        $isNew = !$article->exists;

        $article->fill([
            'category_id'  => static::categoryFor($endpoint)->id,
            'title'        => $endpoint->summary ?: $endpoint->code,
            'content'      => static::buildContent($endpoint),
            'is_published' => true,
            'is_global'    => true,
        ]);
        $article->save();

        return $isNew ? 'created' : 'updated';
    }

    protected static function unpublish(HubEndpoint $endpoint): string
    {
        $article = \Aero\Docs\Models\Article::where('tenant_id', null)
            ->where('slug', static::articleSlug($endpoint))
            ->first();

        if ($article && $article->is_published) {
            $article->is_published = false;
            $article->save();
        }

        return 'unpublished';
    }

    protected static function available(): bool
    {
        return class_exists(\Aero\Docs\Models\Article::class) && class_exists(\Aero\Docs\Models\Category::class);
    }

    protected static function articleSlug(HubEndpoint $endpoint): string
    {
        return 'hub-' . $endpoint->code;
    }

    // -------------------------------------------------------------------
    // Árbol de categorías: Hub → división → categoría del spec
    // -------------------------------------------------------------------

    protected static function rootCategory(): \Aero\Docs\Models\Category
    {
        $category = \Aero\Docs\Models\Category::firstOrNew(['tenant_id' => null, 'slug' => 'hub']);
        $category->fill([
            'name'        => trans('aero.hub::lang.menu.hub'),
            'icon'        => '🔌',
            'description' => trans('aero.hub::lang.settings.menu_description'),
            'is_active'   => true,
            'is_global'   => true,
        ]);
        $category->save();

        return $category;
    }

    protected static function divisionCategory(string $division): \Aero\Docs\Models\Category
    {
        $isAi = $division === 'ai_models';
        $slug = $isAi ? 'hub-modelos' : 'hub-apis';

        $category = \Aero\Docs\Models\Category::firstOrNew(['tenant_id' => null, 'slug' => $slug]);
        $category->fill([
            'parent_id' => static::rootCategory()->id,
            'name'      => trans($isAi ? 'aero.hub::lang.menu.ai_models' : 'aero.hub::lang.menu.apis'),
            'icon'      => $isAi ? '🧠' : '🗂️',
            'is_active' => true,
            'is_global' => true,
        ]);
        $category->save();

        return $category;
    }

    /**
     * Una sub-categoría por cada `HubEndpoint->category` (tag del spec, ej.
     * "SEO", "AI Media", "Amazon") — el slug lleva el prefijo `hub-` para no
     * chocar con una categoría de otro plugin que use el mismo nombre
     * genérico (el slug de Category es único en TODO Aero.Docs, no por rama).
     */
    protected static function categoryFor(HubEndpoint $endpoint): \Aero\Docs\Models\Category
    {
        $slug = 'hub-' . Str::slug($endpoint->category);

        $category = \Aero\Docs\Models\Category::firstOrNew(['tenant_id' => null, 'slug' => $slug]);
        $category->fill([
            'parent_id' => static::divisionCategory($endpoint->division)->id,
            'name'      => $endpoint->category,
            'is_active' => true,
            'is_global' => true,
        ]);
        $category->save();

        return $category;
    }

    // -------------------------------------------------------------------
    // Contenido del artículo
    // -------------------------------------------------------------------

    protected static function buildContent(HubEndpoint $endpoint): string
    {
        $lines = [];

        $lines[] = '# ' . ($endpoint->summary ?: $endpoint->code);
        $lines[] = '';

        if ($description = static::cleanDescription($endpoint->description)) {
            $lines[] = $description;
            $lines[] = '';
        }

        $lines[] = '## Endpoint';
        $lines[] = '';
        $lines[] = '```';
        $lines[] = $endpoint->method . ' /hub' . $endpoint->path;
        $lines[] = '```';
        $lines[] = '';

        $lines[] = '## Autenticación';
        $lines[] = '';
        $lines[] = 'Mandá tu API key en la cabecera `Authorization` — ver '
            . '[Autenticación, permisos y límites](/documentacion/api-autenticacion). '
            . 'Esta llamada requiere el permiso (scope) `' . CatalogSync::scopeFor($endpoint->category) . '`.';
        $lines[] = '';

        if ($credit = CatalogSync::creditHintFor($endpoint)) {
            $lines[] = '## Costo';
            $lines[] = '';
            $lines[] = 'Se cobra **' . $credit['label'] . '**, solo si la llamada fue exitosa.';
            $lines[] = '';
        }

        if ($params = static::paramsTable($endpoint)) {
            $lines[] = '## Parámetros';
            $lines[] = '';
            $lines[] = $params;
            $lines[] = '';
        }

        $lines[] = '---';
        $lines[] = '';
        $lines[] = '_Mismo endpoint que expone [YepAPI](https://docs.yepapi.com/) — mismos parámetros, '
            . 'misma respuesta. Lo único que cambia acá es el dominio: en vez del propio de YepAPI, '
            . 'usás `/hub` bajo tu dominio de Market, con tu API key y pagando en tus créditos._';

        return implode("\n", $lines);
    }

    /** Saca el "Cost: $x/call." (u otras variantes) del final — ya lo mostramos aparte, en su propia sección. */
    protected static function cleanDescription(?string $description): ?string
    {
        if (!$description) {
            return null;
        }

        $clean = trim((string) preg_replace('/\s*Cost:\s*~?\$[0-9.]+[^.]*\.?\s*$/i', '', $description));

        return $clean ?: null;
    }

    protected static function paramsTable(HubEndpoint $endpoint): ?string
    {
        // $endpoint->request_schema es un string JSON crudo: `jsonable` de
        // October solo decodifica al hacer toArray()/toJson(), no en el
        // acceso directo al atributo (ver HasJsonable::addJsonableAttributesToArray()).
        $schema = is_string($endpoint->request_schema)
            ? json_decode($endpoint->request_schema, true)
            : $endpoint->request_schema;
        $properties = $schema['properties'] ?? null;

        if (!is_array($properties) || !$properties) {
            return null;
        }

        $required = array_flip((array) ($schema['required'] ?? []));

        $rows = ["| Parámetro | Tipo | Requerido | Descripción |", "|---|---|---|---|"];

        foreach ($properties as $name => $prop) {
            $type = is_array($prop) ? ($prop['type'] ?? 'string') : 'string';
            $desc = is_array($prop) ? (string) ($prop['description'] ?? '') : '';
            $desc = str_replace(['|', "\n"], ['\\|', ' '], $desc);
            $isRequired = isset($required[$name]) ? 'Sí' : 'No';

            $rows[] = "| `{$name}` | {$type} | {$isRequired} | {$desc} |";
        }

        return implode("\n", $rows);
    }
}
