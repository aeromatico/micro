<?php namespace Aero\Shop\Tests;

use Aero\Shop\Classes\Workflows\OrderNodes;
use Aero\Shop\Models\ChatSession;
use Illuminate\Support\Facades\DB;

require_once __DIR__ . '/ShopTestCase.php';

class OrderNodesTest extends ShopTestCase
{
    protected const PHONE = '59170000001';

    protected array $ids = [];

    protected function seedStore(): void
    {
        $this->openStore(1);
        $camis = $this->collection(1, 'Camisetas', 1);
        $tazas = $this->collection(1, 'Tazas', 2);

        $this->ids['negra'] = $this->product(1, $camis, 'Camiseta negra', 120, extra: ['description' => 'Algodón peruano']);
        $this->ids['blanca'] = $this->product(1, $camis, 'Camiseta blanca', 110);
        $this->ids['taza'] = $this->product(1, $tazas, 'Taza mágica', 45);
        $this->ids['agotada'] = $this->product(1, $tazas, 'Taza agotada', 30, extra: ['track_inventory' => true, 'stock_quantity' => 0]);
        $this->ids['pocas'] = $this->product(1, $tazas, 'Taza limitada', 30, extra: ['track_inventory' => true, 'stock_quantity' => 2]);
        $this->ids['docena'] = $this->product(1, $tazas, 'Vaso por docena', 5, extra: ['min_quantity' => 12]);
        $this->ids['borrador'] = $this->product(1, $tazas, 'Taza borrador', 9, 'draft');
        $this->ids['interno'] = $this->product(1, null, 'Venta libre', 1, 'active', true);
        $this->ids['ajeno'] = $this->product(2, $this->collection(2, 'Ajena', 1), 'Camiseta ajena', 5);

        // Producto con variantes.
        $this->ids['polera'] = $this->product(1, $camis, 'Polera', 80, extra: ['has_variants' => true]);
        foreach ([['M', 80], ['L', 90]] as [$label, $price]) {
            $this->ids['polera_' . $label] = DB::table('aero_shop_product_variants')->insertGetId([
                'tenant_id' => 1, 'product_id' => $this->ids['polera'], 'sku' => 'POL-' . $label, 'price' => $price,
                'stock_quantity' => 5, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    protected function data(array $extra = []): array
    {
        return ['contact' => static::PHONE] + $extra;
    }

    protected function chatSession(int $tenant = 1): ChatSession
    {
        return ChatSession::forContact($tenant, 'p:' . static::PHONE);
    }

    // -- Buscar -----------------------------------------------------------

    public function testTokensDropFillerWords(): void
    {
        $this->assertSame(['camiseta', 'negra'], OrderNodes::tokens('Hola, busco una camiseta negra por favor'));
        $this->assertSame([], OrderNodes::tokens('hola quiero'));
        $this->assertSame(['taza'], OrderNodes::tokens('TAZA taza'));
    }

    public function testSearchRanksAndFiltersByTenantAndStatus(): void
    {
        $this->seedStore();

        $r = OrderNodes::search($this->data(['query' => 'busco una camiseta negra']), [], 1);
        $this->assertSame('found', $r['handle']);
        $this->assertSame(['Camiseta negra'], array_column($r['output']['items'], 'name'));
        $this->assertStringContainsString('1. Camiseta negra — Bs 120.00', $r['output']['text']);

        $all = OrderNodes::search($this->data(['query' => 'camiseta']), [], 1);
        $this->assertContains('Camiseta blanca', array_column($all['output']['items'], 'name'));
        $this->assertNotContains('Camiseta ajena', array_column($all['output']['items'], 'name'));

        // Por categoría (token = nombre de la colección), sin borradores ni internos.
        $tazas = OrderNodes::search($this->data(['query' => 'tazas']), [], 1);
        $names = array_column($tazas['output']['items'], 'name');
        $this->assertContains('Taza mágica', $names);
        $this->assertNotContains('Taza borrador', $names);
        $this->assertNotContains('Venta libre', $names);

        $this->assertSame('empty', OrderNodes::search($this->data(['query' => 'zzzz']), [], 1)['handle']);
        $this->assertSame('empty', OrderNodes::search($this->data(['query' => 'hola']), [], 1)['handle']);
        $this->assertSame('empty', OrderNodes::search($this->data(['query' => 'camiseta', 'category' => 'no existe']), [], 1)['handle']);
    }

    public function testSearchReadsCustomerMessageAndRemembersTheList(): void
    {
        $this->seedStore();

        $ctx = ['trigger' => ['data' => [['body' => 'taza mágica']]]];
        $r = OrderNodes::search($this->data(), $ctx, 1);

        $this->assertSame('found', $r['handle']);
        $list = $this->chatSession()->getList();
        $this->assertSame('products', $list['type']);
        $this->assertSame('Taza mágica', $list['items'][0]['name']);
    }

    // -- Interpretar el pedido ---------------------------------------------

    public static function orderTextProvider(): array
    {
        return [
            ['2', '2', null], ['2 x3', '2', 3], ['2×3', '2', 3], ['2 camisetas negras', 'camisetas negras', 2],
            ['camiseta x3', 'camiseta', 3], ['3x camiseta', 'camiseta', 3], ['quiero una taza', 'taza', 1],
            ['dame dos tazas por favor', 'tazas', 2], ['Camiseta negra', 'Camiseta negra', null], ['  agrega la taza mágica  ', 'la taza mágica', null],
        ];
    }

    /** @dataProvider orderTextProvider */
    public function testParseOrderText(string $text, string $ref, ?int $qty): void
    {
        $this->assertSame([$ref, $qty], OrderNodes::parseOrderText($text));
    }

    // -- Agregar al pedido -------------------------------------------------

    public function testAddByNumberFromTheLastList(): void
    {
        $this->seedStore();
        OrderNodes::search($this->data(['query' => 'camiseta negra']), [], 1);

        $r = OrderNodes::cartAdd($this->data(['product' => '1', 'quantity' => 2]), [], 1);

        $this->assertSame('added', $r['handle']);
        $this->assertSame(2, $r['output']['quantity']);
        $this->assertSame(240.0, $r['output']['cart_total']);
        $this->assertStringContainsString('2 × Camiseta negra', $r['output']['text']);

        // Sumar de nuevo la misma línea la acumula.
        $again = OrderNodes::cartAdd($this->data(['product' => '1 x3']), [], 1);
        $this->assertSame(5, $again['output']['cart_count']);
        $this->assertCount(1, $this->chatSession()->getCart());
    }

    public function testAddByNameQuantityAndErrors(): void
    {
        $this->seedStore();

        $r = OrderNodes::cartAdd($this->data(['product' => 'quiero dos tazas mágicas']), [], 1);
        $this->assertSame('added', $r['handle']);
        $this->assertSame(2, $r['output']['quantity']);

        $this->assertSame('not_found', OrderNodes::cartAdd($this->data(['product' => 'pizza']), [], 1)['handle']);
        $this->assertSame('not_found', OrderNodes::cartAdd($this->data(['product' => '7']), [], 1)['handle']);
        $this->assertSame('not_found', OrderNodes::cartAdd($this->data(['product' => '']), [], 1)['handle']);

        // Borradores, internos y de otro tenant no se pueden pedir ni por id.
        foreach (['borrador', 'interno', 'ajeno'] as $key) {
            $this->assertSame('not_found', OrderNodes::cartAdd($this->data(['product_id' => $this->ids[$key]]), [], 1)['handle'], $key);
        }
    }

    public function testPlainWordsSearchInsteadOfAddingUnlessThereIsPurchaseIntent(): void
    {
        $this->seedStore();

        // «negra» a secas no agrega nada: el flujo debe pasar a buscar.
        $this->assertSame('not_found', OrderNodes::cartAdd($this->data(['product' => 'negra']), [], 1)['handle']);
        $this->assertSame([], $this->chatSession()->getCart());

        // El nombre exacto, un verbo de compra o una cantidad sí agregan.
        $this->assertSame('added', OrderNodes::cartAdd($this->data(['product' => 'Camiseta negra']), [], 1)['handle']);
        $this->assertSame('added', OrderNodes::cartAdd($this->data(['product' => 'quiero la taza mágica']), [], 1)['handle']);
        $this->assertSame('added', OrderNodes::cartAdd($this->data(['product' => '2 blanca']), [], 1)['handle']);
    }

    public function testAddRespectsStockAndMinimumQuantity(): void
    {
        $this->seedStore();

        $this->assertSame('unavailable', OrderNodes::cartAdd($this->data(['product_id' => $this->ids['agotada']]), [], 1)['handle']);
        $this->assertSame('unavailable', OrderNodes::cartAdd($this->data(['product_id' => $this->ids['docena'], 'quantity' => 3]), [], 1)['handle']);
        $this->assertSame('added', OrderNodes::cartAdd($this->data(['product_id' => $this->ids['docena'], 'quantity' => 12]), [], 1)['handle']);

        // Stock 2: puede pedir 2, pero no 1 más (cuenta lo que ya tiene en el pedido).
        $this->assertSame('added', OrderNodes::cartAdd($this->data(['product_id' => $this->ids['pocas'], 'quantity' => 2]), [], 1)['handle']);
        $over = OrderNodes::cartAdd($this->data(['product_id' => $this->ids['pocas'], 'quantity' => 1]), [], 1);
        $this->assertSame('unavailable', $over['handle']);
        $this->assertStringContainsString('ya tienes 2', $over['output']['text']);
    }

    public function testAmbiguousNameAsksToChooseAndTheNumberCompletesIt(): void
    {
        $this->seedStore();

        $r = OrderNodes::cartAdd($this->data(['product' => '2 camiseta']), [], 1);
        $this->assertSame('choose', $r['handle']);
        $this->assertStringContainsString('Responde con el número', $r['output']['text']);

        // El cliente responde «1»: se toma el producto 1 de la lista de opciones y la cantidad pedida (2).
        $done = OrderNodes::cartAdd($this->data(['product' => '1']), [], 1);
        $this->assertSame('added', $done['handle']);
        $this->assertSame(2, $done['output']['quantity']);
    }

    public function testVariantsMustBeChosenFirst(): void
    {
        $this->seedStore();
        OrderNodes::search($this->data(['query' => 'polera']), [], 1);

        $choose = OrderNodes::cartAdd($this->data(['product' => '1']), [], 1);
        $this->assertSame('choose', $choose['handle']);
        $this->assertStringContainsString('opciones', $choose['output']['text']);
        $this->assertSame('variants', $this->chatSession()->getList()['type']);

        $added = OrderNodes::cartAdd($this->data(['product' => '2']), [], 1);
        $this->assertSame('added', $added['handle']);
        $this->assertSame(90.0, $added['output']['cart_total']);

        // La lista de variantes ya se consumió: «2» ya no vuelve a elegir variante.
        $this->assertNotSame('variants', $this->chatSession()->getList()['type'] ?? null);
    }

    public function testCategoryNumbersAreNotProductNumbers(): void
    {
        $this->seedStore();
        $this->chatSession()->remember(['type' => 'categories', 'items' => [['n' => 1, 'id' => 1, 'name' => 'Camisetas']]]);

        $r = OrderNodes::cartAdd($this->data(['product' => '1']), [], 1);

        $this->assertSame('not_found', $r['handle']);
        $this->assertStringContainsString('categoría', $r['output']['reason']);
    }

    public function testCategoryNodeLetsProductNumbersPassToTheNextNode(): void
    {
        $this->seedStore();

        // Sin lista previa (prueba manual) «2» sigue siendo la categoría 2 del menú recalculado.
        $this->assertSame('found', \Aero\Shop\Classes\Workflows\CatalogNodes::products($this->data(['category' => '2']), [], 1)['handle']);

        // Después de ver una lista de productos, «2» ya no es categoría: sale por «no entendí» para que el flujo lo agregue.
        OrderNodes::search($this->data(['query' => 'camiseta']), [], 1);
        $this->assertSame('not_found', \Aero\Shop\Classes\Workflows\CatalogNodes::products($this->data(['category' => '2']), [], 1)['handle']);

        // Pero por nombre sí se entiende una categoría nueva.
        $this->assertSame('found', \Aero\Shop\Classes\Workflows\CatalogNodes::products($this->data(['category' => 'tazas']), [], 1)['handle']);

        // Y tras un menú, «2» vuelve a ser categoría.
        \Aero\Shop\Classes\Workflows\CatalogNodes::categories($this->data(), [], 1);
        $this->assertSame('found', \Aero\Shop\Classes\Workflows\CatalogNodes::products($this->data(['category' => '2']), [], 1)['handle']);
    }

    public function testNeedsAnIdentifiableCustomer(): void
    {
        $this->seedStore();

        $this->expectException(\RuntimeException::class);
        OrderNodes::cartAdd(['product' => 'taza'], [], 1);
    }

    // -- Ver / editar -------------------------------------------------------

    public function testViewRemoveAndClear(): void
    {
        $this->seedStore();
        OrderNodes::cartAdd($this->data(['product_id' => $this->ids['negra'], 'quantity' => 2]), [], 1);
        OrderNodes::cartAdd($this->data(['product_id' => $this->ids['taza']]), [], 1);

        $view = OrderNodes::cart($this->data(), [], 1);
        $this->assertSame('found', $view['handle']);
        $this->assertSame(285.0, $view['output']['total']);
        $this->assertStringContainsString('1. 2 × Camiseta negra — Bs 240.00', $view['output']['text']);

        $this->assertSame('not_found', OrderNodes::cart($this->data(['action' => 'remove', 'item' => 'pizza']), [], 1)['handle']);

        $removed = OrderNodes::cart($this->data(['action' => 'remove', 'item' => '1']), [], 1);
        $this->assertSame('found', $removed['handle']);
        $this->assertSame(45.0, $removed['output']['total']);

        $byName = OrderNodes::cart($this->data(['action' => 'remove', 'item' => 'taza']), [], 1);
        $this->assertSame('empty', $byName['handle']);

        OrderNodes::cartAdd($this->data(['product_id' => $this->ids['taza']]), [], 1);
        $this->assertSame('empty', OrderNodes::cart($this->data(['action' => 'clear']), [], 1)['handle']);
        $this->assertSame([], $this->chatSession()->getCart());
    }

    public function testCartIsPrivatePerCustomerAndTenant(): void
    {
        $this->seedStore();
        OrderNodes::cartAdd($this->data(['product_id' => $this->ids['taza']]), [], 1);

        $this->assertSame('empty', OrderNodes::cart(['contact' => '59170000002'], [], 1)['handle']);

        $this->openStore(2);
        $this->assertSame('empty', OrderNodes::cart($this->data(), [], 2)['handle']);
    }

    public function testRemovedProductsDisappearFromTheCart(): void
    {
        $this->seedStore();
        OrderNodes::cartAdd($this->data(['product_id' => $this->ids['taza']]), [], 1);
        DB::table('aero_shop_products')->where('id', $this->ids['taza'])->update(['status' => 'archived']);

        $view = OrderNodes::cart($this->data(), [], 1);

        $this->assertSame('empty', $view['handle']);
        $this->assertSame([], $this->chatSession()->getCart());
    }

    // -- Confirmar ----------------------------------------------------------

    public function testCheckoutCreatesTheRealOrderWithCatalogPrices(): void
    {
        $this->seedStore();
        OrderNodes::cartAdd($this->data(['product_id' => $this->ids['negra'], 'quantity' => 2]), [], 1);
        OrderNodes::cartAdd($this->data(['product_id' => $this->ids['taza']]), [], 1);

        $r = OrderNodes::checkout($this->data(['customer_name' => 'Ana', 'notes' => 'Sin timbre']), [], 1);

        $this->assertSame('created', $r['handle'], $r['output']['reason'] ?? '');
        $this->assertSame(285.0, $r['output']['total']);
        $this->assertStringStartsWith('ORD-', $r['output']['order_number']);

        $order = DB::table('aero_shop_orders')->where('id', $r['output']['order_id'])->first();
        $this->assertSame(1, (int) $order->tenant_id);
        $this->assertSame('chat', $order->source);
        $this->assertSame('pending', $order->status);
        $this->assertSame('Sin timbre', $order->customer_notes);
        $this->assertSame(2, DB::table('aero_shop_order_items')->where('order_id', $order->id)->count());
        $this->assertSame('Ana', DB::table('aero_shop_customers')->where('id', $order->customer_id)->value('first_name'));
        $this->assertSame(static::PHONE, DB::table('aero_shop_customers')->where('id', $order->customer_id)->value('phone'));

        // El pedido vació el carrito: confirmar dos veces no crea dos pedidos.
        $this->assertSame([], $this->chatSession()->getCart());
        $this->assertSame('empty', OrderNodes::checkout($this->data(), [], 1)['handle']);
        $this->assertSame(1, DB::table('aero_shop_orders')->count());
    }

    public function testCheckoutNeedsAnAddressWhenShippingIsRequiredAndUsesTheCapturedLocation(): void
    {
        $this->seedStore();
        $envio = $this->product(1, null, 'Con envío', 50, extra: ['requires_shipping' => true]);

        OrderNodes::cartAdd($this->data(['product_id' => $envio]), [], 1);

        $needs = OrderNodes::checkout($this->data(), [], 1);
        $this->assertSame('needs_address', $needs['handle']);
        $this->assertSame(0, DB::table('aero_shop_orders')->count());
        $this->assertCount(1, $this->chatSession()->getCart(), 'El carrito se conserva si falta la dirección.');

        // Con la variable `ubicacion` del nodo «Capturar ubicación».
        $ctx = ['vars' => ['ubicacion' => ['lat' => -16.5, 'lng' => -68.15, 'name' => 'Casa de Ana']]];
        $ok = OrderNodes::checkout($this->data(), $ctx, 1);

        $this->assertSame('created', $ok['handle'], $ok['output']['reason'] ?? '');
        $address = DB::table('aero_shop_addresses')->first();
        $this->assertEqualsWithDelta(-16.5, (float) $address->latitude, 0.0001);
        $this->assertSame('Casa de Ana', $address->address_line1);
    }

    public function testCheckoutReportsStockProblemsAndKeepsTheCart(): void
    {
        $this->seedStore();
        OrderNodes::cartAdd($this->data(['product_id' => $this->ids['pocas'], 'quantity' => 2]), [], 1);
        DB::table('aero_shop_products')->where('id', $this->ids['pocas'])->update(['stock_quantity' => 1]);

        $r = OrderNodes::checkout($this->data(), [], 1);

        $this->assertSame('error', $r['handle']);
        $this->assertStringContainsString('Stock insuficiente', $r['output']['reason']);
        $this->assertCount(1, $this->chatSession()->getCart());
        $this->assertSame(0, DB::table('aero_shop_orders')->count());
    }

    public function testCheckoutErrorsWithoutPhoneOrClosedStore(): void
    {
        $this->seedStore();
        OrderNodes::cartAdd(['contact' => 'cliente-sin-telefono', 'product_id' => $this->ids['taza']], [], 1);

        $noPhone = OrderNodes::checkout(['contact' => 'cliente-sin-telefono'], [], 1);
        $this->assertSame('error', $noPhone['handle']);

        DB::table('aero_shop_settings')->where('tenant_id', 1)->update(['is_enabled' => 0]);
        $this->expectException(\RuntimeException::class);
        OrderNodes::checkout($this->data(), [], 1);
    }

    public function testNodesAreDeclaredForTheEditor(): void
    {
        $defs = OrderNodes::definitions();

        $this->assertSame(['shop.search', 'shop.cart_add', 'shop.cart', 'shop.checkout'], array_keys($defs));
        $this->assertSame(['added', 'choose', 'unavailable', 'not_found'], array_column($defs['shop.cart_add']['handles'], 'id'));
        $this->assertSame(['created', 'empty', 'needs_address', 'error'], array_column($defs['shop.checkout']['handles'], 'id'));
    }
}
