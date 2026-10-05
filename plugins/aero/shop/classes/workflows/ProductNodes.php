<?php namespace Aero\Shop\Classes\Workflows;

use Aero\Shop\Models\ChatSession;
use Aero\Shop\Models\Collection;
use Aero\Shop\Models\Product;
use Aero\Shop\Models\ProductVariant;
use Aero\Shop\Models\ShopSettings;

/**
 * Nodo «Tienda › Ver producto»: arma la tarjeta de un producto (foto, nombre,
 * precio, descripción, opciones y stock) y, en un chat real, la envía por Hello
 * con la foto y el texto como pie de foto. Además deja preparada la compra
 * rápida: la tarjeta recuerda qué puede responder el cliente («1» agrega el
 * producto, «1 x3» lleva 3, o el número de la opción si tiene variantes) y el
 * nodo «Agregar al pedido» lo entiende.
 *
 * El tenant es el del workflow. El envío por defecto es «auto»: solo se envía
 * cuando el flujo lo dispara un mensaje entrante real, para que una prueba
 * manual con un teléfono inventado no mande WhatsApp de verdad.
 */
class ProductNodes
{
    /** Los pies de foto de WhatsApp admiten 1024 caracteres. */
    protected const CAPTION_LIMIT = 1000;

    protected const VIEW_PREFIX = '/^(ver|ve|mira|mirar|muestra|muestrame|mostrar|info|informacion|detalle|detalles|foto|fotos|imagen|tarjeta|cuentame|dime)\b\s*(?:(?:de|del|sobre)\s+)?(?:(?:la|el|los|las)\s+)?(?:(?:producto|numero|n|opcion)\s+)?#?\s*/iu';

    /** Reemplazable en pruebas: fn (int $tenantId, string $phone, string $caption, ?string $imageUrl, ?int $accountId): void */
    public static $sender = null;

    public static function definitions(): array
    {
        return [
            'shop.product' => [
                'label'    => 'Tienda › Ver producto (tarjeta)',
                'category' => 'action',
                'handler'  => [static::class, 'product'],
                'handles'  => [['id' => 'found', 'label' => 'tarjeta lista'], ['id' => 'choose', 'label' => 'debe elegir'], ['id' => 'not_found', 'label' => 'no entendí']],
                'fields'   => [
                    ['key' => 'product', 'label' => 'Producto a mostrar', 'type' => 'text', 'hint' => 'Vacío = lo que escribió el cliente: «ver 2» (de la última lista), «2» o el nombre.'],
                    ['key' => 'product_id', 'label' => 'ID de producto (fijo)', 'type' => 'number'],
                    ['key' => 'send', 'label' => 'Enviar la tarjeta al cliente', 'type' => 'select', 'options' => [
                        ['value' => 'auto', 'label' => 'Automático (solo en un chat real)'], ['value' => '1', 'label' => 'Siempre'], ['value' => '0', 'label' => 'Nunca (solo armarla)'],
                    ], 'hint' => 'Envía la foto con el texto por WhatsApp. Por defecto, solo cuando escribió un cliente.'],
                    ['key' => 'account_id', 'label' => 'ID de cuenta de WhatsApp (opcional)', 'type' => 'number'],
                    ['key' => 'contact', 'label' => 'Cliente (teléfono)', 'type' => 'text', 'hint' => 'Vacío = el cliente que escribió.'],
                    ['key' => 'save_as', 'label' => 'Guardar en la variable', 'type' => 'text', 'hint' => 'Por defecto «producto»: {{ vars.producto.text }}, .image_url, .product.name, .sent'],
                ],
            ],
        ];
    }

    public static function product(array $data, array $ctx, ?int $tenantId): array
    {
        CatalogNodes::requireShop($tenantId);

        $var = CatalogNodes::varName($data['save_as'] ?? null, 'producto');
        $contact = ChatContact::resolve($data, $ctx, $tenantId);
        $session = $contact ? ChatSession::forContact($tenantId, $contact['key']) : null;
        $list = $session ? $session->getList() : [];

        $product = null;

        if (is_numeric($data['product_id'] ?? null) && (int) $data['product_id'] > 0) {
            $product = OrderNodes::findProduct($tenantId, (int) $data['product_id']);
        }
        else {
            $ref = static::cleanViewText(trim((string) ($data['product'] ?? '')) ?: CatalogNodes::messageText($ctx));

            if ($ref === '') {
                return static::fail('not_found', $var, 'No se indicó qué producto ver.');
            }

            if (preg_match('/^\d{1,3}$/', $ref)) {
                if (!$session) {
                    return static::fail('not_found', $var, 'No pude identificar al cliente para entender ese número.');
                }

                $resolved = OrderNodes::resolveFromList($tenantId, $list, (int) $ref);

                if (is_string($resolved)) {
                    return static::fail('not_found', $var, $resolved);
                }

                $product = $resolved[0];
            }
            else {
                $found = OrderNodes::resolveByName($tenantId, $ref, $list, true);

                if ($found['status'] === 'choose') {
                    return static::choose($data, $ctx, $tenantId, $var, $found['products'], $list);
                }

                $product = $found['status'] === 'ok' ? $found['product'] : null;

                if (!$product) {
                    return static::fail('not_found', $var, "No encontré un producto que coincida con «{$ref}».");
                }
            }
        }

        if (!$product) {
            return static::fail('not_found', $var, 'Ese producto ya no está disponible.');
        }

        $card = static::card($tenantId, $product);

        if ($session && $card['in_stock']) {
            static::rememberForPurchase($session, $tenantId, $product, $card, $list);
        }

        $delivery = static::deliver($data, $ctx, $tenantId, $contact, $card['text'], $card['image_url']);

        return [
            'output' => [
                'found' => true, 'product' => $card['product'], 'image_url' => $card['image_url'], 'text' => $card['text'],
                'variants' => $card['variants'],
            ] + $delivery,
            'handle' => 'found',
            'var'    => $var,
        ];
    }

    // ------------------------------------------------------------------
    // Tarjeta
    // ------------------------------------------------------------------

    /** @return array{product: array, text: string, image_url: ?string, in_stock: bool, variants: array} */
    protected static function card(int $tenantId, Product $product): array
    {
        $currency = CatalogNodes::currency($tenantId);
        $price = (float) $product->display_price;
        $priceText = ($product->has_price_range ? 'desde ' : '') . $currency->format($price);
        $compare = $product->compare_at_price ? (float) $product->compare_at_price : null;
        $discount = $compare && !$product->has_variants && $compare > $price ? (int) round(($compare - $price) / $compare * 100) : null;
        $category = $product->collection_id ? Collection::forTenant($tenantId)->find($product->collection_id) : null;
        $inStock = (bool) $product->is_in_stock;

        $variants = [];
        $variantLines = [];

        if ($product->has_variants) {
            $rows = ProductVariant::forTenant($tenantId)->where('product_id', $product->id)->where('is_active', true)->get();

            foreach ($rows->values() as $i => $variant) {
                $label = $variant->label ?: ($variant->sku ?: 'Opción ' . ($i + 1));
                $available = !static::tracksStock($tenantId, $product) || $product->allow_backorder || $variant->stock_quantity > 0;
                $variants[] = [
                    'n' => $i + 1, 'id' => (int) $variant->id, 'label' => $label, 'price' => (float) $variant->price,
                    'price_text' => $currency->format((float) $variant->price), 'in_stock' => $available,
                ];
                $variantLines[] = ($i + 1) . ". {$label} — " . $currency->format((float) $variant->price) . ($available ? '' : ' (agotado)');
            }
        }

        $lines = ["*{$product->name}*"];
        $lines[] = $discount
            ? '~' . $currency->format($compare) . '~ *' . $currency->format($price) . "* (-{$discount}%)"
            : "*{$priceText}*";

        if ($category) {
            $lines[] = "_{$category->name}_";
        }

        $description = $product->description ? trim(preg_replace('/\s+/u', ' ', strip_tags($product->description))) : '';

        $footer = [];

        if ($variantLines) {
            $footer[] = "Opciones:\n" . implode("\n", $variantLines);
        }

        $stockNote = static::stockNote($tenantId, $product, $inStock);

        if ($stockNote) {
            $footer[] = $stockNote;
        }

        $footer[] = static::buyPrompt($product, $inStock, count($variants));

        // El pie de foto no pasa de 1024: se recorta la descripción, nunca el llamado a comprar.
        $fixed = implode("\n", $lines) . "\n\n" . implode("\n\n", $footer);
        $room = max(0, static::CAPTION_LIMIT - mb_strlen($fixed) - 4);

        if ($description !== '' && $room > 20) {
            $description = mb_strlen($description) > $room ? rtrim(mb_substr($description, 0, $room - 1)) . '…' : $description;
            $lines[] = '';
            $lines[] = $description;
        }

        $text = implode("\n", $lines) . "\n\n" . implode("\n\n", $footer);

        return [
            'product' => [
                'id' => (int) $product->id, 'name' => $product->name, 'price' => $price, 'price_text' => $priceText,
                'compare_at_text' => $discount ? $currency->format($compare) : null, 'discount_percent' => $discount,
                'description' => $description !== '' ? $description : null, 'category' => $category?->name, 'in_stock' => $inStock,
                'has_variants' => (bool) $product->has_variants,
            ],
            'text'      => $text,
            'image_url' => static::imageUrl($product),
            'in_stock'  => $inStock,
            'variants'  => $variants,
        ];
    }

    protected static function buyPrompt(Product $product, bool $inStock, int $variantCount): string
    {
        if (!$inStock) {
            return 'Por ahora está agotado.';
        }

        if ($product->has_variants) {
            return $variantCount
                ? 'Responde con el número de la opción para agregarla a tu pedido (o *2 x3* para llevar 3).'
                : 'Por ahora no tiene opciones disponibles.';
        }

        $min = (int) $product->min_quantity;

        return $min > 1
            ? "Responde *1 x{$min}* para agregarlo a tu pedido (mínimo {$min} unidades)."
            : 'Responde *1* para agregarlo a tu pedido (o *1 x3* para llevar 3).';
    }

    protected static function stockNote(int $tenantId, Product $product, bool $inStock): ?string
    {
        if (!$inStock || !static::tracksStock($tenantId, $product) || $product->allow_backorder) {
            return null;
        }

        $stock = (int) $product->display_stock;

        return $stock > 0 && $stock <= 5 ? "⚠️ Quedan solo {$stock}." : null;
    }

    protected static function tracksStock(int $tenantId, Product $product): bool
    {
        return ShopSettings::inventoryEnabledForTenant($tenantId) && (bool) $product->track_inventory;
    }

    /** URL absoluta de la primera foto (los pies de foto necesitan una URL pública). */
    protected static function imageUrl(Product $product): ?string
    {
        $path = $product->images->first()?->path ?? null;

        if (!$path) {
            return null;
        }

        return preg_match('~^https?://~i', $path) ? $path : rtrim((string) config('app.url'), '/') . '/' . ltrim($path, '/');
    }

    /**
     * Deja lista la compra rápida: «1» agrega este producto o, si tiene
     * variantes, su número de opción. Debajo conserva la lista donde estaba el cliente.
     */
    protected static function rememberForPurchase(ChatSession $session, int $tenantId, Product $product, array $card, array $previous): void
    {
        $prev = ($previous['type'] ?? null) === 'products' ? $previous : (array) ($previous['prev'] ?? []);

        if ($card['variants']) {
            $session->remember([
                'type' => 'variants', 'product_id' => (int) $product->id, 'qty' => null, 'prev' => $prev,
                'items' => array_map(fn ($v) => ['n' => $v['n'], 'id' => $v['id'], 'name' => $v['label']], $card['variants']),
            ]);

            return;
        }

        $session->remember([
            'type' => 'choose', 'qty' => null, 'prev' => $prev,
            'items' => [['n' => 1, 'id' => (int) $product->id, 'name' => $product->name]],
        ]);
    }

    // ------------------------------------------------------------------
    // Varias coincidencias
    // ------------------------------------------------------------------

    protected static function choose(array $data, array $ctx, int $tenantId, string $var, array $products, array $previous): array
    {
        $items = OrderNodes::presentProducts($tenantId, $products);

        CatalogNodes::remember($data, $ctx, $tenantId, [
            'type' => 'choose', 'qty' => null, 'prev' => ($previous['type'] ?? null) === 'products' ? $previous : (array) ($previous['prev'] ?? []),
            'items' => array_map(fn ($i) => ['n' => $i['n'], 'id' => $i['id'], 'name' => $i['name']], $items),
        ]);

        $text = "Encontré varios productos:\n\n" . OrderNodes::itemLines($items, false) . "\n\nEscribe *ver 1* (o el número que quieras) para ver su tarjeta.";

        return ['output' => ['found' => false, 'reason' => 'Hay varias opciones.', 'items' => $items, 'text' => $text], 'handle' => 'choose', 'var' => $var];
    }

    // ------------------------------------------------------------------
    // Envío
    // ------------------------------------------------------------------

    /**
     * Envía la tarjeta (foto + texto) por Hello. Nunca rompe el flujo: si no se
     * puede enviar, el nodo igual entrega la tarjeta y explica por qué no salió.
     *
     * @return array{sent: bool, send_reason: ?string}
     */
    protected static function deliver(array $data, array $ctx, int $tenantId, ?array $contact, string $caption, ?string $imageUrl): array
    {
        $mode = (string) ($data['send'] ?? '') === '' ? 'auto' : (string) $data['send'];

        if ($mode === '0') {
            return ['sent' => false, 'send_reason' => 'Envío desactivado en el nodo.'];
        }

        $message = (array) ($ctx['trigger']['data'][0] ?? []);

        if ($mode === 'auto' && empty($message['contact_id'])) {
            return ['sent' => false, 'send_reason' => 'Modo automático: solo se envía cuando escribe un cliente (no en pruebas).'];
        }

        if (!$contact || empty($contact['phone'])) {
            return ['sent' => false, 'send_reason' => 'No hay un cliente con teléfono para enviarle la tarjeta.'];
        }

        $accountId = is_numeric($data['account_id'] ?? null) && (int) $data['account_id'] > 0 ? (int) $data['account_id'] : (isset($message['account_id']) ? (int) $message['account_id'] : null);

        try {
            if (is_callable(static::$sender)) {
                call_user_func(static::$sender, $tenantId, $contact['phone'], $caption, $imageUrl, $accountId);
            }
            else {
                static::sendViaHello($tenantId, $contact['phone'], $caption, $imageUrl, $accountId);
            }
        }
        catch (\Throwable $e) {
            return ['sent' => false, 'send_reason' => 'No se pudo enviar: ' . $e->getMessage()];
        }

        return ['sent' => true, 'send_reason' => null];
    }

    protected static function sendViaHello(int $tenantId, string $phone, string $caption, ?string $imageUrl, ?int $accountId): void
    {
        if (!class_exists(\Aero\Hello\Classes\Hello::class)) {
            throw new \RuntimeException('Aero.Hello no está instalado.');
        }

        $options = ['tenant_id' => $tenantId];

        if ($accountId) {
            // La cuenta debe ser del mismo tenant que el workflow.
            $account = \Aero\Hello\Models\Account::where('id', $accountId)->where('tenant_id', $tenantId)->first();

            if (!$account) {
                throw new \RuntimeException('La cuenta indicada no pertenece a este tenant.');
            }

            $options['account_id'] = $account->id;
        }

        if ($imageUrl) {
            $options['media_url'] = $imageUrl;
            $options['media_type'] = 'image';
        }

        \Aero\Hello\Classes\Hello::send($phone, $caption, $options);
    }

    // ------------------------------------------------------------------

    /** «ver 2» → «2», «info de la camiseta negra» → «camiseta negra». */
    public static function cleanViewText(string $text): string
    {
        $t = trim(preg_replace('/\s+/u', ' ', $text));
        $t = preg_replace(static::VIEW_PREFIX, '', $t);

        return trim($t, " .,!¡¿?");
    }

    protected static function fail(string $handle, string $var, string $reason): array
    {
        return ['output' => ['found' => false, 'reason' => $reason, 'text' => $reason], 'handle' => $handle, 'var' => $var];
    }
}
