<?php namespace Aero\Shop\Classes\Workflows;

use Aero\Shop\Models\Collection;
use Aero\Shop\Models\Currency;
use Aero\Shop\Models\Product;
use Aero\Shop\Models\ShopSettings;

/**
 * Nodos de Aero.Workflows para el catálogo de la tienda (se registran por el
 * evento `aero.workflows.registerNodes`, ver Plugin::bootWorkflowsIntegration).
 *
 *   shop.categories → menú numerado de categorías (colecciones) listo para enviar.
 *   shop.products   → productos de una categoría, que entiende lo que el cliente
 *                     respondió al menú: «2», «postres» o el código de la categoría.
 *
 * Se entienden porque comparten UNA sola función de menú (`menu()`): el nodo de
 * productos toma las opciones (padre, incluir vacías, máximo) del nodo
 * «Menú de categorías» de su mismo flujo, así que el «2» significa lo mismo
 * aunque la respuesta llegue en otra ejecución (el menú no se guarda: es
 * determinista). Si ambos corren en la misma ejecución, se usa el menú ya armado.
 *
 * El tenant SIEMPRE es el del workflow ($tenantId), nunca uno de la entrada.
 */
class CatalogNodes
{
    public const MENU_NODE = 'shop.categories';
    public const DEFAULT_MENU_VAR = 'categorias';
    public const DEFAULT_PRODUCTS_VAR = 'productos';

    public static function definitions(): array
    {
        $yesNo = [['value' => '1', 'label' => 'Sí'], ['value' => '0', 'label' => 'No']];

        return [
            self::MENU_NODE => [
                'label'    => 'Tienda › Menú de categorías',
                'category' => 'action',
                'handler'  => [static::class, 'categories'],
                'handles'  => [['id' => 'found', 'label' => 'con categorías'], ['id' => 'empty', 'label' => 'sin categorías']],
                'fields'   => [
                    ['key' => 'title', 'label' => 'Título del menú', 'type' => 'text', 'hint' => 'Por defecto: «¿Qué te gustaría ver?»'],
                    ['key' => 'footer', 'label' => 'Texto al final', 'type' => 'text', 'hint' => 'Por defecto: «Responde con el número o el nombre.»'],
                    ['key' => 'parent', 'label' => 'Subcategorías de…', 'type' => 'text', 'hint' => 'Vacío = categorías principales. O el nombre/código de una categoría.'],
                    ['key' => 'include_empty', 'label' => 'Incluir categorías sin productos', 'type' => 'select', 'options' => $yesNo, 'hint' => 'Por defecto: No.'],
                    ['key' => 'max_items', 'label' => 'Máximo de categorías', 'type' => 'number', 'hint' => 'Por defecto 10 (máx. 30).'],
                    ['key' => 'save_as', 'label' => 'Guardar en la variable', 'type' => 'text', 'hint' => 'Por defecto «categorias»: {{ vars.categorias.text }}, .items, .count'],
                ],
            ],
            'shop.products' => [
                'label'    => 'Tienda › Productos de una categoría',
                'category' => 'action',
                'handler'  => [static::class, 'products'],
                'handles'  => [
                    ['id' => 'found', 'label' => 'con productos'],
                    ['id' => 'empty', 'label' => 'categoría vacía'],
                    ['id' => 'not_found', 'label' => 'no entendí'],
                ],
                'fields'   => [
                    ['key' => 'category', 'label' => 'Categoría elegida', 'type' => 'text', 'hint' => 'Vacío = lo que escribió el cliente: el número del menú, el nombre o el código de la categoría.'],
                    ['key' => 'category_id', 'label' => 'ID de categoría (fijo)', 'type' => 'number', 'hint' => 'Opcional: para mostrar siempre la misma categoría.'],
                    ['key' => 'query', 'label' => 'Buscar dentro (texto)', 'type' => 'text'],
                    ['key' => 'limit', 'label' => 'Máximo de productos', 'type' => 'number', 'hint' => 'Por defecto 8 (máx. 20).'],
                    ['key' => 'only_in_stock', 'label' => 'Solo con stock', 'type' => 'select', 'options' => $yesNo, 'hint' => 'Por defecto: No (los agotados se marcan).'],
                    ['key' => 'show_description', 'label' => 'Mostrar descripción', 'type' => 'select', 'options' => $yesNo, 'hint' => 'Por defecto: Sí.'],
                    ['key' => 'footer', 'label' => 'Texto al final', 'type' => 'text'],
                    ['key' => 'save_as', 'label' => 'Guardar en la variable', 'type' => 'text', 'hint' => 'Por defecto «productos»: {{ vars.productos.text }}, .items, .category'],
                ],
            ],
        ];
    }

    // ------------------------------------------------------------------
    // Nodo: menú de categorías
    // ------------------------------------------------------------------

    public static function categories(array $data, array $ctx, ?int $tenantId): array
    {
        static::requireShop($tenantId);

        $options = static::menuOptions($data);
        $items = static::menu($tenantId, $options);
        $var = static::varName($data['save_as'] ?? null, static::DEFAULT_MENU_VAR);

        if (!$items) {
            return [
                'output' => ['found' => false, 'count' => 0, 'items' => [], 'text' => '', 'reason' => 'La tienda no tiene categorías para mostrar.'],
                'handle' => 'empty',
                'var'    => $var,
            ];
        }

        static::remember($data, $ctx, $tenantId, [
            'type'  => 'categories',
            'items' => array_map(fn ($i) => ['n' => $i['n'], 'id' => $i['id'], 'name' => $i['name']], $items),
        ]);

        return [
            'output' => ['found' => true, 'count' => count($items), 'items' => $items, 'text' => static::menuText($items, $data)],
            'handle' => 'found',
            'var'    => $var,
        ];
    }

    /**
     * Guarda la última lista mostrada al cliente para entender su «2» después.
     * Sin cliente identificable (pruebas manuales) no hace nada: los nodos
     * siguen funcionando con el menú recalculado.
     */
    public static function remember(array $data, array $ctx, int $tenantId, array $list): void
    {
        try {
            $contact = ChatContact::resolve($data, $ctx, $tenantId);

            if ($contact) {
                \Aero\Shop\Models\ChatSession::forContact($tenantId, $contact['key'])->remember($list);
            }
        }
        catch (\Throwable $e) {
            // Recordar es una ayuda, nunca debe romper el nodo.
        }
    }

    /**
     * Menú numerado: activas, orden manual y luego nombre, con el conteo de
     * productos activos (propios + de sus subcategorías). La ÚNICA fuente de
     * verdad de qué significa «2».
     *
     * @return array<int, array{n:int, id:int, name:string, slug:?string, products_count:int}>
     */
    public static function menu(int $tenantId, array $options): array
    {
        $parentId = null;

        if ($options['parent'] !== '') {
            $parent = static::findCollection($tenantId, $options['parent']);

            if (!$parent) {
                return [];
            }

            $parentId = (int) $parent->id;
        }

        $collections = Collection::forTenant($tenantId)->where('is_active', true)
            ->when($parentId, fn ($q) => $q->where('parent_id', $parentId), fn ($q) => $q->whereNull('parent_id'))
            ->orderBy('sort_order')->orderBy('name')->get();

        $items = [];

        foreach ($collections as $collection) {
            $count = static::productCount($tenantId, static::scopeIds($tenantId, $collection->id));

            if ($count === 0 && !$options['include_empty']) {
                continue;
            }

            $items[] = ['id' => (int) $collection->id, 'name' => $collection->name, 'slug' => $collection->slug, 'products_count' => $count];

            if (count($items) >= $options['max_items']) {
                break;
            }
        }

        foreach ($items as $i => &$item) {
            $item = ['n' => $i + 1] + $item;
        }

        return $items;
    }

    protected static function menuText(array $items, array $data): string
    {
        $title = trim((string) ($data['title'] ?? '')) ?: '¿Qué te gustaría ver?';
        $footer = trim((string) ($data['footer'] ?? '')) ?: 'Responde con el número o el nombre.';

        $lines = array_map(fn ($i) => "{$i['n']}. {$i['name']}", $items);

        return "*{$title}*\n\n" . implode("\n", $lines) . "\n\n{$footer}";
    }

    protected static function menuOptions(array $data): array
    {
        return [
            'parent'        => trim((string) ($data['parent'] ?? '')),
            'include_empty' => static::truthy($data['include_empty'] ?? null, false),
            'max_items'     => max(1, min(30, (int) ($data['max_items'] ?? 0) ?: 10)),
        ];
    }

    // ------------------------------------------------------------------
    // Nodo: productos de una categoría
    // ------------------------------------------------------------------

    public static function products(array $data, array $ctx, ?int $tenantId, $run = null, array $node = []): array
    {
        static::requireShop($tenantId);

        $linked = static::linkedMenu($run, $node);
        $menuOptions = static::menuOptions($linked['data']);
        $var = static::varName($data['save_as'] ?? null, static::DEFAULT_PRODUCTS_VAR);

        $category = null;
        $choice = '';

        if (is_numeric($data['category_id'] ?? null) && (int) $data['category_id'] > 0) {
            $category = Collection::forTenant($tenantId)->where('is_active', true)->find((int) $data['category_id']);
            $choice = '#' . (int) $data['category_id'];
        }
        else {
            $choice = trim((string) ($data['category'] ?? '')) ?: static::messageText($ctx);
            $category = static::resolveCategory($tenantId, $choice, $ctx, $menuOptions, $linked['var'], $data);
        }

        if (!$category) {
            $menu = static::menu($tenantId, $menuOptions);

            return [
                'output' => [
                    'found' => false, 'count' => 0, 'items' => [], 'text' => '', 'choice' => $choice,
                    'reason' => 'No se entendió qué categoría eligió el cliente.',
                    'menu_text' => $menu ? static::menuText($menu, $linked['data']) : '',
                ],
                'handle' => 'not_found',
                'var'    => $var,
            ];
        }

        $limit = max(1, min(20, (int) ($data['limit'] ?? 0) ?: 8));
        $showDescription = static::truthy($data['show_description'] ?? null, true);
        $search = trim((string) ($data['query'] ?? ''));

        $query = Product::forTenant($tenantId)->active()
            ->whereIn('collection_id', static::scopeIds($tenantId, (int) $category->id))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%")));

        $all = $query->with('images')->orderBy('name')->get();

        if (static::truthy($data['only_in_stock'] ?? null, false)) {
            $all = $all->filter(fn (Product $p) => $p->is_in_stock)->values();
        }

        $currency = static::currency($tenantId);
        $items = [];

        foreach ($all->take($limit) as $i => $product) {
            $price = (float) $product->display_price;
            $items[] = [
                'n'           => $i + 1,
                'id'          => (int) $product->id,
                'name'        => $product->name,
                'price'       => $price,
                'price_text'  => ($product->has_price_range ? 'desde ' : '') . $currency->format($price),
                'description' => $product->description ? mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags($product->description))), 0, 120) : null,
                'in_stock'    => (bool) $product->is_in_stock,
                'image_url'   => $product->images->first()?->path,
            ];
        }

        $categoryOut = ['id' => (int) $category->id, 'name' => $category->name, 'slug' => $category->slug];

        if (!$items) {
            return [
                'output' => ['found' => false, 'count' => 0, 'total' => 0, 'items' => [], 'category' => $categoryOut, 'choice' => $choice, 'text' => '', 'reason' => "La categoría «{$category->name}» no tiene productos disponibles."],
                'handle' => 'empty',
                'var'    => $var,
            ];
        }

        static::remember($data, $ctx, $tenantId, [
            'type'     => 'products',
            'category' => (int) $category->id,
            'items'    => array_map(fn ($i) => ['n' => $i['n'], 'id' => $i['id'], 'name' => $i['name']], $items),
        ]);

        return [
            'output' => [
                'found' => true, 'count' => count($items), 'total' => $all->count(), 'items' => $items,
                'category' => $categoryOut, 'choice' => $choice,
                'text' => static::productsText($categoryOut, $items, $showDescription, trim((string) ($data['footer'] ?? ''))),
            ],
            'handle' => 'found',
            'var'    => $var,
        ];
    }

    protected static function productsText(array $category, array $items, bool $showDescription, string $footer): string
    {
        $lines = [];

        foreach ($items as $item) {
            $lines[] = "{$item['n']}. {$item['name']} — {$item['price_text']}" . ($item['in_stock'] ? '' : ' (agotado)');

            if ($showDescription && $item['description']) {
                $lines[] = '   _' . $item['description'] . '_';
            }
        }

        return "*{$category['name']}*\n\n" . implode("\n", $lines) . ($footer !== '' ? "\n\n{$footer}" : '');
    }

    /**
     * Qué categoría quiso decir el cliente: un número del menú, el nombre
     * (sin importar mayúsculas ni tildes) o el código (slug).
     */
    protected static function resolveCategory(int $tenantId, string $choice, array $ctx, array $menuOptions, string $menuVar, array $data = []): ?Collection
    {
        $choice = trim($choice);

        if ($choice === '') {
            return null;
        }

        // «2», «2.», «2)» → posición en el menú. Si el menú ya se armó en esta
        // misma ejecución se usa tal cual; si no, se reconstruye igual que el nodo.
        if (preg_match('/^\s*(\d{1,3})\s*[.)\-]?\s*$/u', $choice, $m)) {
            $items = $ctx['vars'][$menuVar]['items'] ?? null;

            if (!is_array($items) || !$items) {
                $remembered = static::rememberedList($data, $ctx, $tenantId);

                // Si lo último que vio el cliente NO fue un menú de categorías (p. ej. una lista de
                // productos), su número no es de categoría: se deja pasar al siguiente nodo.
                if ($remembered && ($remembered['type'] ?? null) !== 'categories') {
                    return null;
                }

                $items = $remembered ? (array) $remembered['items'] : static::menu($tenantId, $menuOptions);
            }

            foreach ($items as $item) {
                if ((int) ($item['n'] ?? 0) === (int) $m[1]) {
                    return Collection::forTenant($tenantId)->where('is_active', true)->find($item['id']);
                }
            }

            return null;
        }

        return static::findCollection($tenantId, $choice);
    }

    /** La última lista que este cliente vio (categorías, productos, variantes), si no caducó. */
    protected static function rememberedList(array $data, array $ctx, int $tenantId): array
    {
        try {
            $contact = ChatContact::resolve($data, $ctx, $tenantId);

            return $contact ? \Aero\Shop\Models\ChatSession::forContact($tenantId, $contact['key'])->getList() : [];
        }
        catch (\Throwable $e) {
            return [];
        }
    }

    /** Por código exacto, nombre exacto o nombre que contiene / está contenido (sin tildes ni mayúsculas). */
    public static function findCollection(int $tenantId, string $text): ?Collection
    {
        $needle = static::normalize($text);

        if ($needle === '') {
            return null;
        }

        $collections = Collection::forTenant($tenantId)->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();

        foreach ($collections as $c) {
            if (static::normalize((string) $c->slug) === $needle || static::normalize($c->name) === $needle) {
                return $c;
            }
        }

        $best = null;

        foreach ($collections as $c) {
            $name = static::normalize($c->name);

            if (mb_strlen($needle) >= 3 && (str_contains($name, $needle) || (mb_strlen($name) >= 3 && str_contains($needle, $name)))) {
                if (!$best || mb_strlen($name) < mb_strlen(static::normalize($best->name))) {
                    $best = $c;
                }
            }
        }

        return $best;
    }

    // ------------------------------------------------------------------
    // Apoyo
    // ------------------------------------------------------------------

    /**
     * El nodo «Menú de categorías» de este flujo: primero uno aguas arriba del
     * nodo actual (conectados), si no el primero del grafo. Devuelve su `data`
     * y el nombre de variable donde deja el menú.
     *
     * @return array{data: array, var: string}
     */
    protected static function linkedMenu($run, array $node): array
    {
        $none = ['data' => [], 'var' => static::DEFAULT_MENU_VAR];

        if (!is_object($run)) {
            return $none;
        }

        $graph = $run->workflow ? $run->workflow->jsonField('graph') : [];
        $nodes = [];

        foreach ((array) ($graph['nodes'] ?? []) as $n) {
            if (!empty($n['id'])) {
                $nodes[$n['id']] = $n;
            }
        }

        $found = null;

        // Aguas arriba (búsqueda en anchura por las aristas hacia atrás).
        if (!empty($node['id'])) {
            $queue = [$node['id']];
            $seen = [$node['id'] => true];

            while ($queue && !$found) {
                $current = array_shift($queue);

                foreach ((array) ($graph['edges'] ?? []) as $edge) {
                    $source = $edge['source'] ?? null;

                    if (($edge['target'] ?? null) !== $current || !$source || isset($seen[$source])) {
                        continue;
                    }

                    $seen[$source] = true;

                    if (($nodes[$source]['type'] ?? null) === static::MENU_NODE) {
                        $found = $nodes[$source];
                        break;
                    }

                    $queue[] = $source;
                }
            }
        }

        // Si no hay conexión directa, el primero del flujo.
        if (!$found) {
            foreach ($nodes as $n) {
                if (($n['type'] ?? null) === static::MENU_NODE) {
                    $found = $n;
                    break;
                }
            }
        }

        if (!$found) {
            return $none;
        }

        $data = (array) ($found['data'] ?? []);

        return ['data' => $data, 'var' => static::varName($data['save_as'] ?? null, static::DEFAULT_MENU_VAR)];
    }

    /** Lo que escribió el cliente (mensaje entrante) o el texto de la entrada. */
    public static function messageText(array $ctx): string
    {
        $trigger = (array) ($ctx['trigger'] ?? []);

        foreach ([$trigger['data'][0]['body'] ?? null, $trigger['body'] ?? null, $trigger['text'] ?? null, $trigger['texto'] ?? null, $trigger['categoria'] ?? null, $trigger['category'] ?? null] as $candidate) {
            if (is_scalar($candidate) && trim((string) $candidate) !== '') {
                return trim((string) $candidate);
            }
        }

        return '';
    }

    /** IDs de la categoría y de sus subcategorías activas. */
    public static function scopeIds(int $tenantId, int $collectionId): array
    {
        $children = Collection::forTenant($tenantId)->where('is_active', true)->where('parent_id', $collectionId)->pluck('id')->all();

        return array_merge([$collectionId], array_map('intval', $children));
    }

    protected static function productCount(int $tenantId, array $collectionIds): int
    {
        return Product::forTenant($tenantId)->active()->whereIn('collection_id', $collectionIds)->count();
    }

    public static function currency(int $tenantId): Currency
    {
        $settings = ShopSettings::forTenant($tenantId)->first();

        return $settings?->base_currency
            ?: Currency::where('code', 'BOB')->first()
            ?: new Currency(['symbol' => 'Bs', 'decimal_places' => 2]);
    }

    public static function requireShop(?int $tenantId): void
    {
        if (!$tenantId) {
            throw new \RuntimeException('El nodo de tienda necesita un workflow con cuenta (tenant).');
        }

        $settings = ShopSettings::forTenant($tenantId)->first();

        if ($settings && !$settings->is_enabled) {
            throw new \RuntimeException('La tienda de este tenant está desactivada (Tienda → Configuración).');
        }
    }

    public static function varName(mixed $value, string $default): string
    {
        $name = preg_replace('/[^A-Za-z0-9_]/', '', (string) $value);

        return $name !== '' ? $name : $default;
    }

    public static function truthy(mixed $value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'si', 'sí', 'yes', 'on'], true);
    }

    public static function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = strtr($text, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $text));
    }
}
