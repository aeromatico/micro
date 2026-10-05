<?php namespace Aero\Shop\Classes\Workflows;

use Aero\Shop\Classes\Exceptions\InsufficientStockException;
use Aero\Shop\Classes\Exceptions\OrderException;
use Aero\Shop\Classes\InventoryService;
use Aero\Shop\Classes\OrderService;
use Aero\Shop\Classes\RestaurantService;
use Aero\Shop\Models\ChatSession;
use Aero\Shop\Models\Collection;
use Aero\Shop\Models\Product;
use Aero\Shop\Models\ProductVariant;
use Db;

/**
 * Nodos de compra por chat para Aero.Workflows:
 *   shop.search     → buscar productos por texto libre («camiseta negra»).
 *   shop.cart_add   → agregar al pedido: «2» (de la última lista), «camiseta» o «2 x3».
 *   shop.cart       → ver, quitar o vaciar el pedido.
 *   shop.checkout   → crear el pedido real con OrderService (el único camino de pedidos).
 *
 * El carrito y la última lista mostrada viven en ChatSession (por tenant y
 * teléfono del cliente), así «2» significa categoría, producto o variante según
 * lo último que vio, aunque llegue en otra ejecución. Precios y stock SIEMPRE
 * del catálogo. El tenant es el del workflow.
 */
class OrderNodes
{
    protected const STOPWORDS = [
        'hola', 'buenas', 'buenos', 'dias', 'tardes', 'noches', 'quiero', 'quisiera', 'busco', 'buscando', 'necesito', 'tienen', 'tienes',
        'tiene', 'hay', 'algun', 'alguna', 'algo', 'un', 'una', 'unos', 'unas', 'el', 'la', 'los', 'las', 'de', 'del', 'para', 'por', 'favor',
        'con', 'que', 'me', 'mi', 'su', 'en', 'y', 'o', 'a', 'al', 'lo', 'le', 'se', 'si', 'gracias', 'precio', 'precios', 'cuanto', 'cuesta',
        'vale', 'ver', 'mostrar', 'muestrame', 'dame', 'puedes', 'puede', 'cual', 'cuales',
    ];

    protected const ORDER_VERBS = '(quiero|quisiera|agrega(me)?|agregar|añade|anade|pon(me)?|dame|me das|pido|ordeno|llevo|voy a llevar|comprar)';

    protected const WORD_NUMBERS = ['un' => 1, 'una' => 1, 'uno' => 1, 'dos' => 2, 'tres' => 3, 'cuatro' => 4, 'cinco' => 5, 'seis' => 6, 'siete' => 7, 'ocho' => 8, 'nueve' => 9, 'diez' => 10];

    public static function definitions(): array
    {
        $yesNo = [['value' => '1', 'label' => 'Sí'], ['value' => '0', 'label' => 'No']];
        $contact = ['key' => 'contact', 'label' => 'Cliente (teléfono)', 'type' => 'text', 'hint' => 'Vacío = el cliente que escribió. Para pruebas: un teléfono, ej. 59170000000.'];

        return [
            'shop.search' => [
                'label' => 'Tienda › Buscar productos', 'category' => 'action', 'handler' => [static::class, 'search'],
                'handles' => [['id' => 'found', 'label' => 'con resultados'], ['id' => 'empty', 'label' => 'sin resultados']],
                'fields' => [
                    ['key' => 'query', 'label' => 'Texto a buscar', 'type' => 'text', 'hint' => 'Vacío = lo que escribió el cliente. Entiende frases: «busco una camiseta negra».'],
                    ['key' => 'category', 'label' => 'Solo en la categoría…', 'type' => 'text', 'hint' => 'Opcional: nombre o código.'],
                    ['key' => 'limit', 'label' => 'Máximo de resultados', 'type' => 'number', 'hint' => 'Por defecto 8 (máx. 20).'],
                    ['key' => 'only_in_stock', 'label' => 'Solo con stock', 'type' => 'select', 'options' => $yesNo, 'hint' => 'Por defecto: No.'],
                    ['key' => 'show_description', 'label' => 'Mostrar descripción', 'type' => 'select', 'options' => $yesNo, 'hint' => 'Por defecto: Sí.'],
                    ['key' => 'footer', 'label' => 'Texto al final', 'type' => 'text'],
                    $contact,
                    ['key' => 'save_as', 'label' => 'Guardar en la variable', 'type' => 'text', 'hint' => 'Por defecto «busqueda»: {{ vars.busqueda.text }}, .items'],
                ],
            ],
            'shop.cart_add' => [
                'label' => 'Tienda › Agregar al pedido', 'category' => 'action', 'handler' => [static::class, 'cartAdd'],
                'handles' => [
                    ['id' => 'added', 'label' => 'agregado'], ['id' => 'choose', 'label' => 'debe elegir'],
                    ['id' => 'unavailable', 'label' => 'no disponible'], ['id' => 'not_found', 'label' => 'no entendí'],
                ],
                'fields' => [
                    ['key' => 'product', 'label' => 'Producto elegido', 'type' => 'text', 'hint' => 'Vacío = lo que escribió el cliente: «2» (de la última lista), «2 x3» (producto 2, cantidad 3) o el nombre («2 camisetas»).'],
                    ['key' => 'product_id', 'label' => 'ID de producto (fijo)', 'type' => 'number'],
                    ['key' => 'quantity', 'label' => 'Cantidad', 'type' => 'number', 'hint' => 'Vacío = la que diga el cliente, o 1.'],
                    $contact,
                    ['key' => 'save_as', 'label' => 'Guardar en la variable', 'type' => 'text', 'hint' => 'Por defecto «pedido»: {{ vars.pedido.text }}, .cart_total, .cart_count'],
                ],
            ],
            'shop.cart' => [
                'label' => 'Tienda › Ver o editar pedido', 'category' => 'action', 'handler' => [static::class, 'cart'],
                'handles' => [['id' => 'found', 'label' => 'con productos'], ['id' => 'empty', 'label' => 'vacío'], ['id' => 'not_found', 'label' => 'no entendí']],
                'fields' => [
                    ['key' => 'action', 'label' => 'Acción', 'type' => 'select', 'options' => [['value' => 'view', 'label' => 'Ver el pedido'], ['value' => 'remove', 'label' => 'Quitar un producto'], ['value' => 'clear', 'label' => 'Vaciar el pedido']]],
                    ['key' => 'item', 'label' => 'Producto a quitar', 'type' => 'text', 'hint' => 'Número de la línea del pedido o nombre. Vacío = lo que escribió el cliente.'],
                    $contact,
                    ['key' => 'save_as', 'label' => 'Guardar en la variable', 'type' => 'text', 'hint' => 'Por defecto «pedido».'],
                ],
            ],
            'shop.checkout' => [
                'label' => 'Tienda › Confirmar pedido', 'category' => 'action', 'handler' => [static::class, 'checkout'],
                'handles' => [
                    ['id' => 'created', 'label' => 'pedido creado'], ['id' => 'empty', 'label' => 'pedido vacío'],
                    ['id' => 'needs_address', 'label' => 'falta dirección'], ['id' => 'error', 'label' => 'error'],
                ],
                'fields' => [
                    ['key' => 'location', 'label' => 'Ubicación de entrega', 'type' => 'text', 'hint' => 'Vacío = la capturada con el nodo «Capturar ubicación» ({{ vars.ubicacion }}).'],
                    ['key' => 'address', 'label' => 'Dirección (texto)', 'type' => 'text'],
                    ['key' => 'city', 'label' => 'Ciudad', 'type' => 'text'],
                    ['key' => 'order_type', 'label' => 'Tipo de pedido (restaurante)', 'type' => 'select', 'options' => [['value' => 'delivery', 'label' => 'Delivery'], ['value' => 'pickup', 'label' => 'Recoger'], ['value' => 'dine_in', 'label' => 'Comer en local']]],
                    ['key' => 'payment_gateway_id', 'label' => 'ID del método de pago', 'type' => 'number', 'hint' => 'Vacío = queda pendiente, sin cobro.'],
                    ['key' => 'customer_name', 'label' => 'Nombre del cliente', 'type' => 'text', 'hint' => 'Vacío = el nombre de su contacto.'],
                    ['key' => 'notes', 'label' => 'Notas del pedido', 'type' => 'text'],
                    $contact,
                    ['key' => 'save_as', 'label' => 'Guardar en la variable', 'type' => 'text', 'hint' => 'Por defecto «orden»: {{ vars.orden.text }}, .order_number, .total, .tracking_url'],
                ],
            ],
        ];
    }

    // ------------------------------------------------------------------
    // Buscar
    // ------------------------------------------------------------------

    public static function search(array $data, array $ctx, ?int $tenantId): array
    {
        CatalogNodes::requireShop($tenantId);

        $var = CatalogNodes::varName($data['save_as'] ?? null, 'busqueda');
        $query = trim((string) ($data['query'] ?? '')) ?: CatalogNodes::messageText($ctx);
        $tokens = static::tokens($query);

        if (!$tokens) {
            return static::emptySearch($var, $query, 'No se indicó qué buscar.');
        }

        $collections = Collection::forTenant($tenantId)->where('is_active', true)->get(['id', 'name'])
            ->mapWithKeys(fn ($c) => [(int) $c->id => CatalogNodes::normalize($c->name)])->all();

        $scope = null;

        if (trim((string) ($data['category'] ?? '')) !== '') {
            $category = CatalogNodes::findCollection($tenantId, (string) $data['category']);

            if (!$category) {
                return static::emptySearch($var, $query, 'No existe esa categoría.');
            }

            $scope = CatalogNodes::scopeIds($tenantId, (int) $category->id);
        }

        // El filtrado se hace en PHP sobre texto normalizado (sin tildes, plurales
        // singularizados): un catálogo de tienda es chico y así «tazas mágicas»
        // encuentra «Taza mágica» en cualquier base de datos.
        $candidates = Product::forTenant($tenantId)->active()
            ->when($scope, fn ($q) => $q->whereIn('collection_id', $scope))
            ->limit(1500)->get();

        $scored = [];

        foreach ($candidates as $product) {
            $name = CatalogNodes::normalize($product->name);
            $desc = CatalogNodes::normalize(strip_tags((string) $product->description));
            $sku = CatalogNodes::normalize((string) $product->sku);
            $collection = $collections[(int) $product->collection_id] ?? '';
            $matched = 0;
            $score = 0;

            foreach ($tokens as $token) {
                $hit = false;

                foreach ([[$name, 3], [$sku, 2], [$collection, 1], [$desc, 1]] as [$field, $weight]) {
                    if ($field !== '' && str_contains($field, $token)) {
                        $score += $weight;
                        $hit = true;
                    }
                }

                $matched += $hit ? 1 : 0;
            }

            if ($matched > 0) {
                $scored[] = ['product' => $product, 'matched' => $matched, 'score' => $score];
            }
        }

        if (!$scored) {
            return static::emptySearch($var, $query, "No encontré productos para «{$query}».");
        }

        // Solo el mejor nivel: si algo coincide con todas las palabras, no se mezclan los que coinciden con una.
        $best = max(array_column($scored, 'matched'));
        $scored = array_values(array_filter($scored, fn ($s) => $s['matched'] === $best));
        usort($scored, fn ($a, $b) => [$b['score'], $a['product']->name] <=> [$a['score'], $b['product']->name]);

        $products = collect($scored)->pluck('product');

        if (CatalogNodes::truthy($data['only_in_stock'] ?? null, false)) {
            $products = $products->filter(fn (Product $p) => $p->is_in_stock)->values();
        }

        $limit = max(1, min(20, (int) ($data['limit'] ?? 0) ?: 8));
        // Imágenes solo de los que se muestran, en el mismo orden del ranking.
        $ids = $products->take($limit)->pluck('id')->all();
        $withImages = Product::with('images')->whereIn('id', $ids)->get()->keyBy('id');
        $items = static::presentProducts($tenantId, array_values(array_filter(array_map(fn ($id) => $withImages[$id] ?? null, $ids))));

        if (!$items) {
            return static::emptySearch($var, $query, "No encontré productos disponibles para «{$query}».");
        }

        CatalogNodes::remember($data, $ctx, $tenantId, [
            'type'  => 'products',
            'items' => array_map(fn ($i) => ['n' => $i['n'], 'id' => $i['id'], 'name' => $i['name']], $items),
        ]);

        $label = mb_strlen($query) > 60 ? mb_substr($query, 0, 60) . '…' : $query;
        $footer = trim((string) ($data['footer'] ?? ''));

        return [
            'output' => [
                'found' => true, 'query' => $query, 'count' => count($items), 'total' => $products->count(), 'items' => $items,
                'text' => "*Esto encontré para «{$label}»*\n\n" . static::itemLines($items, CatalogNodes::truthy($data['show_description'] ?? null, true)) . ($footer !== '' ? "\n\n{$footer}" : ''),
            ],
            'handle' => 'found',
            'var'    => $var,
        ];
    }

    protected static function emptySearch(string $var, string $query, string $reason): array
    {
        return ['output' => ['found' => false, 'query' => $query, 'count' => 0, 'items' => [], 'text' => '', 'reason' => $reason], 'handle' => 'empty', 'var' => $var];
    }

    /** @return array<int, string> palabras útiles: sin signos, sin tildes, sin muletillas. */
    public static function tokens(string $text): array
    {
        $words = array_filter(explode(' ', CatalogNodes::normalize($text)), fn ($w) => mb_strlen($w) >= 2 && !in_array($w, static::STOPWORDS, true));

        return array_values(array_unique(array_map([static::class, 'singular'], $words)));
    }

    /** «camisetas» → «camiseta», «botones» → «boton»: se busca por subcadena, así que basta con quitar la terminal. */
    protected static function singular(string $word): string
    {
        if (mb_strlen($word) > 4 && str_ends_with($word, 'es')) {
            return mb_substr($word, 0, -2);
        }

        return mb_strlen($word) > 3 && str_ends_with($word, 's') ? mb_substr($word, 0, -1) : $word;
    }

    protected static function containsAny(string $haystack, array $tokens): bool
    {
        foreach ($tokens as $token) {
            if (str_contains($haystack, $token)) {
                return true;
            }
        }

        return false;
    }

    // ------------------------------------------------------------------
    // Agregar al pedido
    // ------------------------------------------------------------------

    public static function cartAdd(array $data, array $ctx, ?int $tenantId): array
    {
        CatalogNodes::requireShop($tenantId);

        $var = CatalogNodes::varName($data['save_as'] ?? null, 'pedido');
        [$contact, $session] = static::sessionFor($data, $ctx, $tenantId);
        $list = $session->getList();

        $explicitQty = is_numeric($data['quantity'] ?? null) && (int) $data['quantity'] >= 1 ? (int) $data['quantity'] : null;
        $text = trim((string) ($data['product'] ?? '')) ?: CatalogNodes::messageText($ctx);
        [$ref, $parsedQty] = static::parseOrderText($text);

        $variant = null;
        $qty = $explicitQty ?? $parsedQty;
        $product = null;
        $consumedList = false;

        if (is_numeric($data['product_id'] ?? null) && (int) $data['product_id'] > 0) {
            $product = static::findProduct($tenantId, (int) $data['product_id']);
        }
        elseif ($ref === '') {
            return static::addResult('not_found', $var, 'No se indicó qué producto agregar.');
        }
        elseif (preg_match('/^\d{1,3}$/', $ref)) {
            $resolved = static::resolveFromList($tenantId, $list, (int) $ref);

            if (is_string($resolved)) {
                return static::addResult('not_found', $var, $resolved);
            }

            [$product, $variant, $listQty] = $resolved;
            $qty = $qty ?? $listQty;
            $consumedList = in_array($list['type'] ?? null, ['variants', 'choose'], true);
        }
        else {
            // «negra» a secas es una búsqueda, no un pedido: solo se agrega por nombre con intención
            // de compra (un verbo como «quiero», una cantidad) o con el nombre exacto.
            $intent = $parsedQty !== null || (bool) preg_match('/^' . static::ORDER_VERBS . '\\b/iu', trim($text));
            $found = static::resolveByName($tenantId, $ref, $list, $intent);

            if ($found['status'] === 'choose') {
                return static::chooseProducts($data, $ctx, $tenantId, $var, $found['products'], $qty ?? 1, $list);
            }

            if ($found['status'] !== 'ok') {
                return static::addResult('not_found', $var, "No encontré un producto que coincida con «{$ref}».");
            }

            $product = $found['product'];
        }

        if (!$product) {
            return static::addResult('not_found', $var, 'Ese producto ya no está disponible.');
        }

        $qty = max(1, min(999, (int) ($qty ?: 1)));

        if ($product->has_variants && !$variant) {
            return static::chooseVariants($data, $ctx, $tenantId, $var, $product, $qty, $list);
        }

        try {
            RestaurantService::resolve($product, []);
        }
        catch (OrderException $e) {
            return static::addResult('unavailable', $var, "«{$product->name}» necesita que elijas opciones: pídelo desde la tienda web.");
        }

        $cart = $session->getCart();
        $key = $product->id . '-' . ($variant?->id ?? 0);
        $existing = 0;

        foreach ($cart as $line) {
            if (($line['product_id'] ?? 0) . '-' . ($line['variant_id'] ?? 0) === $key) {
                $existing = (int) $line['quantity'];
            }
        }

        $newQty = $existing + $qty;

        if ($newQty < (int) $product->min_quantity) {
            return static::addResult('unavailable', $var, "«{$product->name}» se pide desde {$product->min_quantity} unidades.");
        }

        if ($newQty > 999 || !(new InventoryService())->checkAvailability($product, $variant, $newQty)) {
            return static::addResult('unavailable', $var, "No tengo stock suficiente de «{$product->name}»" . ($existing ? " (ya tienes {$existing} en tu pedido)." : '.'));
        }

        $found = false;

        foreach ($cart as &$line) {
            if (($line['product_id'] ?? 0) . '-' . ($line['variant_id'] ?? 0) === $key) {
                $line['quantity'] = $newQty;
                $found = true;
            }
        }
        unset($line);

        if (!$found) {
            $cart[] = ['product_id' => (int) $product->id, 'variant_id' => $variant ? (int) $variant->id : null, 'quantity' => $qty];
        }

        $session->setCart($cart);

        // Una lista de variantes/opciones ya cumplió su función: se vuelve a la lista de productos que había debajo.
        if ($consumedList) {
            $session->remember((array) ($list['prev'] ?? []));
        }

        $summary = static::summary($tenantId, $session);
        $label = $product->name . ($variant ? " ({$variant->label})" : '');

        return [
            'output' => [
                'added' => true, 'product' => ['id' => (int) $product->id, 'name' => $product->name], 'variant' => $variant?->label,
                'quantity' => $qty, 'cart_count' => $summary['units'], 'cart_total' => $summary['total'], 'cart_total_text' => $summary['total_text'],
                'cart_text' => $summary['text'], 'text' => "✅ Agregué {$qty} × {$label}.\n\n" . $summary['text'],
            ],
            'handle' => 'added',
            'var'    => $var,
        ];
    }

    protected static function addResult(string $handle, string $var, string $reason): array
    {
        return ['output' => ['added' => false, 'reason' => $reason, 'text' => $reason], 'handle' => $handle, 'var' => $var];
    }

    /** @return array{0: Product, 1: ?ProductVariant, 2: ?int}|string producto elegido o el motivo por el que no se pudo */
    protected static function resolveFromList(int $tenantId, array $list, int $n): array|string
    {
        $type = $list['type'] ?? null;

        if (!$list || !in_array($type, ['products', 'variants', 'choose'], true)) {
            return $type === 'categories'
                ? 'Eso es el número de una categoría. Elige una categoría y luego el producto.'
                : 'No tengo una lista reciente para elegir. Busca un producto o elige una categoría primero.';
        }

        $item = collect($list['items'] ?? [])->firstWhere('n', $n);

        if (!$item) {
            return "No hay una opción {$n} en la lista.";
        }

        if ($type === 'variants') {
            $product = static::findProduct($tenantId, (int) ($list['product_id'] ?? 0));
            $variant = $product ? ProductVariant::forTenant($tenantId)->where('product_id', $product->id)->where('is_active', true)->find($item['id']) : null;

            return $product && $variant ? [$product, $variant, isset($list['qty']) ? (int) $list['qty'] : null] : 'Esa opción ya no está disponible.';
        }

        $product = static::findProduct($tenantId, (int) $item['id']);

        return $product ? [$product, null, $type === 'choose' && isset($list['qty']) ? (int) $list['qty'] : null] : 'Ese producto ya no está disponible.';
    }

    /** @return array{status: string, product?: Product, products?: array<int, Product>} */
    protected static function resolveByName(int $tenantId, string $ref, array $list, bool $intent = true): array
    {
        $needle = CatalogNodes::normalize($ref);
        $tokens = static::tokens($ref) ?: array_values(array_filter(explode(' ', $needle)));

        // Primero lo que el cliente tiene a la vista.
        if (($list['type'] ?? null) === 'products') {
            foreach ((array) $list['items'] as $item) {
                if (CatalogNodes::normalize((string) $item['name']) === $needle) {
                    $product = static::findProduct($tenantId, (int) $item['id']);

                    if ($product) {
                        return ['status' => 'ok', 'product' => $product];
                    }
                }
            }
        }

        if (!$tokens) {
            return ['status' => 'not_found'];
        }

        $matches = Product::forTenant($tenantId)->active()->orderBy('name')->limit(1500)->get()
            ->filter(function (Product $p) use ($tokens) {
                $name = CatalogNodes::normalize($p->name);

                foreach ($tokens as $token) {
                    if (!str_contains($name, $token)) {
                        return false;
                    }
                }

                return true;
            })->values()->take(8);

        if ($matches->isEmpty()) {
            return ['status' => 'not_found'];
        }

        $exact = $matches->first(fn ($p) => CatalogNodes::normalize($p->name) === $needle);

        if (!$exact && !$intent) {
            return ['status' => 'not_found'];
        }

        if ($exact || $matches->count() === 1) {
            return ['status' => 'ok', 'product' => $exact ?: $matches->first()];
        }

        return ['status' => 'choose', 'products' => $matches->all()];
    }

    protected static function chooseProducts(array $data, array $ctx, int $tenantId, string $var, array $products, int $qty, array $prev): array
    {
        $items = static::presentProducts($tenantId, $products);

        CatalogNodes::remember($data, $ctx, $tenantId, [
            'type' => 'choose', 'qty' => $qty, 'prev' => ($prev['type'] ?? null) === 'products' ? $prev : [],
            'items' => array_map(fn ($i) => ['n' => $i['n'], 'id' => $i['id'], 'name' => $i['name']], $items),
        ]);

        $text = "Encontré varios productos, ¿cuál quieres?\n\n" . static::itemLines($items, false) . "\n\nResponde con el número.";

        return ['output' => ['added' => false, 'reason' => 'Hay varias opciones.', 'items' => $items, 'text' => $text], 'handle' => 'choose', 'var' => $var];
    }

    protected static function chooseVariants(array $data, array $ctx, int $tenantId, string $var, Product $product, int $qty, array $prev): array
    {
        $currency = CatalogNodes::currency($tenantId);
        $variants = ProductVariant::forTenant($tenantId)->where('product_id', $product->id)->where('is_active', true)->get();

        if ($variants->isEmpty()) {
            return static::addResult('unavailable', $var, "«{$product->name}» no tiene opciones disponibles.");
        }

        $items = [];
        $lines = [];

        foreach ($variants->values() as $i => $variant) {
            $n = $i + 1;
            $items[] = ['n' => $n, 'id' => (int) $variant->id, 'name' => $variant->label];
            $lines[] = "{$n}. {$variant->label} — " . $currency->format((float) $variant->price) . ($variant->stock_quantity > 0 || !$product->track_inventory ? '' : ' (agotado)');
        }

        CatalogNodes::remember($data, $ctx, $tenantId, [
            'type' => 'variants', 'product_id' => (int) $product->id, 'qty' => $qty,
            'prev' => ($prev['type'] ?? null) === 'products' ? $prev : (array) ($prev['prev'] ?? []), 'items' => $items,
        ]);

        $text = "*{$product->name}* tiene estas opciones:\n\n" . implode("\n", $lines) . "\n\nResponde con el número de la opción.";

        return ['output' => ['added' => false, 'reason' => 'Debe elegir una opción.', 'product' => ['id' => (int) $product->id, 'name' => $product->name], 'items' => $items, 'text' => $text], 'handle' => 'choose', 'var' => $var];
    }

    /**
     * «2» → ['2', null] · «2 x3» → ['2', 3] · «2 camisetas» → ['camisetas', 2] ·
     * «camiseta x3» → ['camiseta', 3] · «quiero una taza» → ['taza', 1].
     *
     * @return array{0: string, 1: ?int}
     */
    public static function parseOrderText(string $text): array
    {
        $t = trim(preg_replace('/\s+/u', ' ', $text));
        $t = trim(preg_replace('/^' . static::ORDER_VERBS . '\\s+/iu', '', $t));
        $t = trim(preg_replace('/\s*(por favor|porfa|gracias)[.!]*$/iu', '', $t), " .,!¡¿?");

        if (preg_match('/^(\d{1,3})\s*[x×*]\s*(\d{1,3})$/iu', $t, $m)) {
            return [$m[1], (int) $m[2]];
        }

        if (preg_match('/^(\d{1,3})\s*[x×*]\s*(.+)$/iu', $t, $m) && preg_match('/\pL/u', $m[2])) {
            return [trim($m[2]), (int) $m[1]];
        }

        if (preg_match('/^(.+?)\s*[x×*]\s*(\d{1,3})$/iu', $t, $m) && preg_match('/\pL/u', $m[1])) {
            return [trim($m[1]), (int) $m[2]];
        }

        if (preg_match('/^(\d{1,3})\s+(.+)$/u', $t, $m) && preg_match('/\pL/u', $m[2])) {
            return [trim($m[2]), (int) $m[1]];
        }

        if (preg_match('/^(un|una|uno|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|diez)\s+(.+)$/iu', $t, $m)) {
            return [trim($m[2]), static::WORD_NUMBERS[mb_strtolower($m[1])]];
        }

        return [$t, null];
    }

    // ------------------------------------------------------------------
    // Ver / editar pedido
    // ------------------------------------------------------------------

    public static function cart(array $data, array $ctx, ?int $tenantId): array
    {
        CatalogNodes::requireShop($tenantId);

        $var = CatalogNodes::varName($data['save_as'] ?? null, 'pedido');
        [, $session] = static::sessionFor($data, $ctx, $tenantId);
        $action = in_array($data['action'] ?? 'view', ['view', 'remove', 'clear'], true) ? ($data['action'] ?? 'view') : 'view';

        if ($action === 'clear') {
            $session->setCart([]);

            return ['output' => ['empty' => true, 'cleared' => true, 'count' => 0, 'total' => 0, 'items' => [], 'text' => 'Vacié tu pedido.'], 'handle' => 'empty', 'var' => $var];
        }

        if ($action === 'remove') {
            $summary = static::summary($tenantId, $session);
            $ref = trim((string) ($data['item'] ?? '')) ?: CatalogNodes::messageText($ctx);
            $index = null;

            if (preg_match('/^\s*(\d{1,3})\s*$/', $ref, $m)) {
                $index = (int) $m[1] - 1;
            }
            else {
                $needle = CatalogNodes::normalize($ref);

                foreach ($summary['items'] as $i => $item) {
                    if ($needle !== '' && str_contains(CatalogNodes::normalize($item['name']), $needle)) {
                        $index = $i;
                        break;
                    }
                }
            }

            if ($index === null || !isset($summary['items'][$index])) {
                return ['output' => ['empty' => false, 'reason' => 'No encontré ese producto en tu pedido.', 'text' => "No encontré ese producto en tu pedido.\n\n" . $summary['text']] + static::summaryOutput($summary), 'handle' => 'not_found', 'var' => $var];
            }

            $target = $summary['items'][$index];
            $cart = array_values(array_filter($session->getCart(), fn ($l) => !((int) $l['product_id'] === $target['product_id'] && (int) ($l['variant_id'] ?? 0) === (int) ($target['variant_id'] ?? 0))));
            $session->setCart($cart);
        }

        $summary = static::summary($tenantId, $session);

        if (!$summary['items']) {
            return ['output' => ['empty' => true, 'count' => 0, 'total' => 0, 'items' => [], 'text' => 'Tu pedido está vacío.'], 'handle' => 'empty', 'var' => $var];
        }

        return ['output' => ['empty' => false] + static::summaryOutput($summary), 'handle' => 'found', 'var' => $var];
    }

    protected static function summaryOutput(array $summary): array
    {
        return [
            'count' => $summary['units'], 'lines' => count($summary['items']), 'total' => $summary['total'], 'total_text' => $summary['total_text'],
            'items' => $summary['items'], 'text' => $summary['text'],
        ];
    }

    // ------------------------------------------------------------------
    // Confirmar pedido
    // ------------------------------------------------------------------

    public static function checkout(array $data, array $ctx, ?int $tenantId): array
    {
        CatalogNodes::requireShop($tenantId);

        $var = CatalogNodes::varName($data['save_as'] ?? null, 'orden');
        $contact = ChatContact::resolve($data, $ctx, $tenantId);

        if (!$contact) {
            throw new \RuntimeException('No pude identificar al cliente: indica su teléfono en el nodo o usa un mensaje entrante.');
        }

        $name = trim((string) ($data['customer_name'] ?? '')) ?: ($contact['name'] ?: 'Cliente');
        $phone = $contact['phone'];

        if (!$phone) {
            return static::checkoutError($var, 'error', 'No tengo el teléfono del cliente para confirmar el pedido.');
        }

        $shipping = static::shipping($data, $ctx);
        $options = array_filter([
            'source'         => 'chat',
            'shipping'       => $shipping,
            'customer_notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'order_type'     => in_array($data['order_type'] ?? '', ['delivery', 'pickup', 'dine_in'], true) ? $data['order_type'] : null,
        ]);
        $gatewayId = is_numeric($data['payment_gateway_id'] ?? null) && (int) $data['payment_gateway_id'] > 0 ? (int) $data['payment_gateway_id'] : null;

        try {
            $order = Db::transaction(function () use ($tenantId, $contact, $name, $phone, $options, $gatewayId) {
                $session = ChatSession::where('tenant_id', $tenantId)->where('contact_key', $contact['key'])->lockForUpdate()->first();
                $cart = $session ? $session->fresh()->getCart() : [];

                if (!$session || !$cart || ($session->expires_at && $session->expires_at->isPast())) {
                    return null;
                }

                $items = array_map(fn ($l) => ['product_id' => $l['product_id'], 'variant_id' => $l['variant_id'] ?? null, 'quantity' => $l['quantity']], $cart);
                $order = (new OrderService())->create($tenantId, $items, ['first_name' => $name, 'phone' => $phone], $gatewayId, $options);
                $session->setCart([]);

                return $order;
            });
        }
        catch (InsufficientStockException $e) {
            return static::checkoutError($var, 'error', $e->getMessage() . ' Ajusta tu pedido e inténtalo de nuevo.');
        }
        catch (OrderException $e) {
            $needsAddress = str_contains(mb_strtolower($e->getMessage()), 'envío') || str_contains(mb_strtolower($e->getMessage()), 'dirección');

            return static::checkoutError($var, $needsAddress ? 'needs_address' : 'error', $e->getMessage());
        }

        if (!$order) {
            return ['output' => ['created' => false, 'reason' => 'El pedido está vacío.', 'text' => 'Tu pedido está vacío.'], 'handle' => 'empty', 'var' => $var];
        }

        $currency = CatalogNodes::currency($tenantId);
        $lines = $order->items->map(fn ($i) => "• {$i->quantity} × {$i->product_name_snapshot}" . ($i->variant_label_snapshot ? " ({$i->variant_label_snapshot})" : '') . ' — ' . $currency->format((float) $i->line_total))->all();
        $url = OrderService::publicUrl($order);
        $total = (float) $order->grand_total;

        return [
            'output' => [
                'created' => true, 'order_id' => (int) $order->id, 'order_number' => $order->order_number, 'status' => $order->status,
                'total' => $total, 'total_text' => $currency->format($total), 'tracking_url' => $url, 'payment_reference' => $order->payment_reference,
                'text' => "✅ *Pedido {$order->order_number} recibido*\n\n" . implode("\n", $lines) . "\n\n*Total: " . $currency->format($total) . "*\n\nSigue tu pedido aquí: {$url}",
            ],
            'handle' => 'created',
            'var'    => $var,
        ];
    }

    protected static function checkoutError(string $var, string $handle, string $reason): array
    {
        return ['output' => ['created' => false, 'reason' => $reason, 'text' => $reason], 'handle' => $handle, 'var' => $var];
    }

    /** Coordenadas de entrega: el campo, o la variable `ubicacion` del nodo «Capturar ubicación». */
    protected static function shipping(array $data, array $ctx): ?array
    {
        $loc = $data['location'] ?? null;

        if (!is_array($loc) && is_string($loc) && preg_match('/(-?\d{1,3}(?:\.\d+)?)\s*,\s*(-?\d{1,3}(?:\.\d+)?)/', $loc, $m)) {
            $loc = ['lat' => $m[1], 'lng' => $m[2]];
        }

        if (!is_array($loc) || (!isset($loc['lat']) && !isset($loc['latitude']))) {
            $loc = $ctx['vars']['ubicacion'] ?? null;
        }

        $shipping = [];

        if (is_array($loc)) {
            $lat = $loc['lat'] ?? $loc['latitude'] ?? null;
            $lng = $loc['lng'] ?? $loc['longitude'] ?? null;

            if (is_numeric($lat) && is_numeric($lng) && abs((float) $lat) <= 90 && abs((float) $lng) <= 180) {
                $shipping += ['latitude' => (float) $lat, 'longitude' => (float) $lng];

                if (!empty($loc['name'])) {
                    $shipping['location_label'] = mb_substr((string) $loc['name'], 0, 120);
                    $shipping['address_line1'] = mb_substr((string) $loc['name'], 0, 190);
                }
            }
        }

        if (trim((string) ($data['address'] ?? '')) !== '') {
            $shipping['address_line1'] = mb_substr(trim((string) $data['address']), 0, 190);
        }

        if (trim((string) ($data['city'] ?? '')) !== '') {
            $shipping['city'] = mb_substr(trim((string) $data['city']), 0, 100);
        }

        return $shipping ?: null;
    }

    // ------------------------------------------------------------------
    // Apoyo
    // ------------------------------------------------------------------

    /** @return array{0: array, 1: ChatSession} */
    protected static function sessionFor(array $data, array $ctx, int $tenantId): array
    {
        $contact = ChatContact::resolve($data, $ctx, $tenantId);

        if (!$contact) {
            throw new \RuntimeException('No pude identificar al cliente: indica su teléfono en el nodo o usa un mensaje entrante.');
        }

        return [$contact, ChatSession::forContact($tenantId, $contact['key'])];
    }

    protected static function findProduct(int $tenantId, int $id): ?Product
    {
        return Product::forTenant($tenantId)->active()->with('images')->find($id);
    }

    /** @param array<int, Product> $products */
    protected static function presentProducts(int $tenantId, array $products): array
    {
        $currency = CatalogNodes::currency($tenantId);
        $items = [];

        foreach (array_values($products) as $i => $product) {
            $price = (float) $product->display_price;
            $items[] = [
                'n' => $i + 1, 'id' => (int) $product->id, 'name' => $product->name, 'price' => $price,
                'price_text' => ($product->has_price_range ? 'desde ' : '') . $currency->format($price),
                'description' => $product->description ? mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags($product->description))), 0, 120) : null,
                'in_stock' => (bool) $product->is_in_stock, 'image_url' => $product->images->first()?->path,
            ];
        }

        return $items;
    }

    protected static function itemLines(array $items, bool $showDescription): string
    {
        $lines = [];

        foreach ($items as $item) {
            $lines[] = "{$item['n']}. {$item['name']} — {$item['price_text']}" . ($item['in_stock'] ? '' : ' (agotado)');

            if ($showDescription && $item['description']) {
                $lines[] = '   _' . $item['description'] . '_';
            }
        }

        return implode("\n", $lines);
    }

    /**
     * El pedido con precios actuales del catálogo. Las líneas de productos que
     * ya no existen se descartan (y se avisa en el texto).
     *
     * @return array{items: array, units: int, total: float, total_text: string, text: string}
     */
    protected static function summary(int $tenantId, ChatSession $session): array
    {
        $currency = CatalogNodes::currency($tenantId);
        $cart = $session->getCart();
        $items = [];
        $kept = [];
        $dropped = [];
        $total = 0.0;
        $units = 0;

        foreach ($cart as $line) {
            $product = static::findProduct($tenantId, (int) $line['product_id']);
            $variant = $product && !empty($line['variant_id'])
                ? ProductVariant::forTenant($tenantId)->where('product_id', $product->id)->where('is_active', true)->find($line['variant_id'])
                : null;

            if (!$product || ($product->has_variants && !$variant)) {
                $dropped[] = $line;
                continue;
            }

            $qty = (int) $line['quantity'];
            $price = $variant ? (float) $variant->price : (float) $product->base_price;
            $lineTotal = round($price * $qty, 4);
            $total += $lineTotal;
            $units += $qty;
            $kept[] = $line;
            $items[] = [
                'n' => count($items) + 1, 'product_id' => (int) $product->id, 'variant_id' => $variant ? (int) $variant->id : null,
                'name' => $product->name . ($variant ? " ({$variant->label})" : ''), 'quantity' => $qty, 'unit_price' => $price,
                'line_total' => $lineTotal, 'line_total_text' => $currency->format($lineTotal),
            ];
        }

        if ($dropped) {
            $session->setCart($kept);
        }

        $total = round($total, 4);
        $lines = array_map(fn ($i) => "{$i['n']}. {$i['quantity']} × {$i['name']} — {$i['line_total_text']}", $items);
        $text = $items
            ? "🛒 *Tu pedido*\n\n" . implode("\n", $lines) . "\n\n*Total: " . $currency->format($total) . '*' . ($dropped ? "\n\n(Quité " . count($dropped) . ' producto(s) que ya no están disponibles.)' : '')
            : 'Tu pedido está vacío.';

        return ['items' => $items, 'units' => $units, 'total' => $total, 'total_text' => $currency->format($total), 'text' => $text];
    }
}
