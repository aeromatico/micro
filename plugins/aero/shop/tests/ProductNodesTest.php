<?php namespace Aero\Shop\Tests;

use Aero\Shop\Classes\Workflows\CatalogNodes;
use Aero\Shop\Classes\Workflows\OrderNodes;
use Aero\Shop\Classes\Workflows\ProductNodes;
use Aero\Shop\Models\ChatSession;
use Illuminate\Support\Facades\DB;

require_once __DIR__ . '/ShopTestCase.php';

class ProductNodesTest extends ShopTestCase
{
    protected const PHONE = '59170000001';

    protected array $ids = [];
    protected array $sent = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->sent = [];
        ProductNodes::$sender = function (int $tenantId, string $phone, string $caption, ?string $image, ?int $account) {
            $this->sent[] = compact('tenantId', 'phone', 'caption', 'image', 'account');
        };
    }

    public function tearDown(): void
    {
        ProductNodes::$sender = null;

        parent::tearDown();
    }

    protected function seedStore(): void
    {
        $this->openStore(1);
        $camis = $this->collection(1, 'Camisetas', 1);

        $this->ids['negra'] = $this->product(1, $camis, 'Camiseta negra', 120, extra: ['description' => 'Algodón peruano de alta calidad', 'compare_at_price' => 150]);
        $this->ids['blanca'] = $this->product(1, $camis, 'Camiseta blanca', 110);
        $this->ids['agotada'] = $this->product(1, $camis, 'Gorra agotada', 30, extra: ['track_inventory' => true, 'stock_quantity' => 0]);
        $this->ids['pocas'] = $this->product(1, $camis, 'Gorra limitada', 30, extra: ['track_inventory' => true, 'stock_quantity' => 3]);
        $this->ids['docena'] = $this->product(1, $camis, 'Vaso por docena', 5, extra: ['min_quantity' => 12]);
        $this->ids['ajeno'] = $this->product(2, $this->collection(2, 'Ajena', 1), 'Camiseta ajena', 5);

        $this->ids['polera'] = $this->product(1, $camis, 'Polera', 80, extra: ['has_variants' => true, 'track_inventory' => true]);
        foreach ([['M', 80, 5], ['L', 90, 0]] as [$label, $price, $stock]) {
            $this->ids['polera_' . $label] = DB::table('aero_shop_product_variants')->insertGetId([
                'tenant_id' => 1, 'product_id' => $this->ids['polera'], 'sku' => 'POL-' . $label, 'price' => $price,
                'stock_quantity' => $stock, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    protected function data(array $extra = []): array
    {
        return ['contact' => static::PHONE, 'send' => '0'] + $extra;
    }

    protected function chatSession(): ChatSession
    {
        return ChatSession::forContact(1, 'p:' . static::PHONE);
    }

    public function testCardHasPriceDiscountDescriptionAndBuyPrompt(): void
    {
        $this->seedStore();

        $r = ProductNodes::product($this->data(['product_id' => $this->ids['negra']]), [], 1);

        $this->assertSame('found', $r['handle']);
        $text = $r['output']['text'];
        $this->assertStringContainsString('*Camiseta negra*', $text);
        $this->assertStringContainsString('~Bs 150.00~ *Bs 120.00* (-20%)', $text);
        $this->assertStringContainsString('_Camisetas_', $text);
        $this->assertStringContainsString('Algodón peruano', $text);
        $this->assertStringContainsString('Responde *1* para agregarlo a tu pedido', $text);
        $this->assertSame(20, $r['output']['product']['discount_percent']);
        $this->assertLessThanOrEqual(1024, mb_strlen($text));
        $this->assertSame('producto', $r['var']);
    }

    public function testCaptionNeverExceedsWhatsappLimitAndKeepsTheCallToAction(): void
    {
        $this->seedStore();
        DB::table('aero_shop_products')->where('id', $this->ids['negra'])->update(['description' => str_repeat('Palabra larga ', 200)]);

        $text = ProductNodes::product($this->data(['product_id' => $this->ids['negra']]), [], 1)['output']['text'];

        $this->assertLessThanOrEqual(1000, mb_strlen($text));
        $this->assertStringContainsString('…', $text);
        $this->assertStringContainsString('Responde *1*', $text);
    }

    public function testStockMessages(): void
    {
        $this->seedStore();

        $low = ProductNodes::product($this->data(['product_id' => $this->ids['pocas']]), [], 1)['output']['text'];
        $this->assertStringContainsString('Quedan solo 3', $low);

        $out = ProductNodes::product($this->data(['product_id' => $this->ids['agotada']]), [], 1);
        $this->assertSame('found', $out['handle']);
        $this->assertStringContainsString('agotado', $out['output']['text']);
        $this->assertStringNotContainsString('Responde *1*', $out['output']['text']);
        $this->assertFalse($out['output']['product']['in_stock']);

        $min = ProductNodes::product($this->data(['product_id' => $this->ids['docena']]), [], 1)['output']['text'];
        $this->assertStringContainsString('Responde *1 x12*', $min);
    }

    public function testResolvesByNumberFromTheLastListAndByName(): void
    {
        $this->seedStore();
        OrderNodes::search($this->data(['query' => 'camiseta']), [], 1);

        $byNumber = ProductNodes::product($this->data(['product' => 'ver 1']), [], 1);
        $this->assertSame('found', $byNumber['handle']);
        $this->assertStringContainsString('Camiseta', $byNumber['output']['product']['name']);

        foreach (['info de la camiseta negra', 'ver camiseta negra', 'Camiseta negra', 'foto camiseta negra'] as $text) {
            $r = ProductNodes::product($this->data(['product' => $text]), [], 1);
            $this->assertSame('found', $r['handle'], $text);
            $this->assertSame('Camiseta negra', $r['output']['product']['name'], $text);
        }

        $this->assertSame('not_found', ProductNodes::product($this->data(['product' => 'ver 9']), [], 1)['handle']);
        $this->assertSame('not_found', ProductNodes::product($this->data(['product' => 'pizza']), [], 1)['handle']);
        $this->assertSame('not_found', ProductNodes::product($this->data(['product' => '']), [], 1)['handle']);
        $this->assertSame('not_found', ProductNodes::product($this->data(['product_id' => $this->ids['ajeno']]), [], 1)['handle']);
    }

    public function testAmbiguousNameListsOptionsAndViewNumberCompletesIt(): void
    {
        $this->seedStore();

        $r = ProductNodes::product($this->data(['product' => 'camiseta']), [], 1);
        $this->assertSame('choose', $r['handle']);
        $this->assertStringContainsString('ver 1', $r['output']['text']);

        $card = ProductNodes::product($this->data(['product' => 'ver 2']), [], 1);
        $this->assertSame('found', $card['handle']);
    }

    public function testQuickBuyOneAddsTheViewedProduct(): void
    {
        $this->seedStore();

        ProductNodes::product($this->data(['product_id' => $this->ids['negra']]), [], 1);

        $added = OrderNodes::cartAdd($this->data(['product' => '1 x3']), [], 1);

        $this->assertSame('added', $added['handle']);
        $this->assertSame(3, $added['output']['quantity']);
        $this->assertSame(360.0, $added['output']['cart_total']);
    }

    public function testQuickBuyAcceptsPlainYes(): void
    {
        $this->seedStore();
        ProductNodes::product($this->data(['product_id' => $this->ids['blanca']]), [], 1);

        foreach (['sí', 'ok', 'agregar', 'quiero'] as $i => $word) {
            ProductNodes::product($this->data(['product_id' => $this->ids['blanca']]), [], 1);
            $this->assertSame('added', OrderNodes::cartAdd($this->data(['product' => $word]), [], 1)['handle'], $word);
        }

        $this->assertSame(4, OrderNodes::cart($this->data(), [], 1)['output']['count']);
    }

    public function testVariantCardListsOptionsAndTheNumberPicksOne(): void
    {
        $this->seedStore();

        $r = ProductNodes::product($this->data(['product_id' => $this->ids['polera']]), [], 1);
        $this->assertStringContainsString('1. POL-M — Bs 80.00', $r['output']['text']);
        $this->assertStringContainsString('2. POL-L — Bs 90.00 (agotado)', $r['output']['text']);
        $this->assertStringContainsString('número de la opción', $r['output']['text']);
        $this->assertCount(2, $r['output']['variants']);

        $this->assertSame('variants', $this->chatSession()->getList()['type']);

        $added = OrderNodes::cartAdd($this->data(['product' => '1 x2']), [], 1);
        $this->assertSame('added', $added['handle']);
        $this->assertSame(160.0, $added['output']['cart_total']);
    }

    public function testAfterBuyingTheCustomerGoesBackToTheListHeWasOn(): void
    {
        $this->seedStore();
        OrderNodes::search($this->data(['query' => 'camiseta']), [], 1);

        ProductNodes::product($this->data(['product' => 'ver 1']), [], 1);
        $this->assertSame('choose', $this->chatSession()->getList()['type']);

        OrderNodes::cartAdd($this->data(['product' => '1']), [], 1);

        $this->assertSame('products', $this->chatSession()->getList()['type'], 'Vuelve a la lista de la búsqueda.');
    }

    public function testSendsTheCardOnlyInARealChatByDefault(): void
    {
        $this->seedStore();
        $data = ['contact' => static::PHONE, 'product_id' => $this->ids['negra']];

        // Prueba manual (sin mensaje entrante): arma la tarjeta pero no la envía.
        $manual = ProductNodes::product($data, [], 1);
        $this->assertFalse($manual['output']['sent']);
        $this->assertSame([], $this->sent);

        // Mensaje entrante real: envía foto + texto al teléfono del cliente.
        $ctx = ['trigger' => ['data' => [['contact_id' => 5, 'account_id' => 7, 'body' => 'ver 1']]]];
        $real = ProductNodes::product($data, $ctx, 1);

        $this->assertTrue($real['output']['sent']);
        $this->assertCount(1, $this->sent);
        $this->assertSame(static::PHONE, $this->sent[0]['phone']);
        $this->assertSame($real['output']['text'], $this->sent[0]['caption']);
        $this->assertSame(7, $this->sent[0]['account']);
        $this->assertSame(1, $this->sent[0]['tenantId']);
    }

    public function testSendModesAndFailuresNeverBreakTheCard(): void
    {
        $this->seedStore();
        $data = ['contact' => static::PHONE, 'product_id' => $this->ids['negra']];

        $always = ProductNodes::product($data + ['send' => '1'], [], 1);
        $this->assertTrue($always['output']['sent']);

        $never = ProductNodes::product($data + ['send' => '0'], ['trigger' => ['data' => [['contact_id' => 5]]]], 1);
        $this->assertFalse($never['output']['sent']);
        $this->assertCount(1, $this->sent);

        ProductNodes::$sender = fn () => throw new \RuntimeException('WhatsApp caído');
        $broken = ProductNodes::product($data + ['send' => '1'], [], 1);
        $this->assertSame('found', $broken['handle']);
        $this->assertFalse($broken['output']['sent']);
        $this->assertStringContainsString('WhatsApp caído', $broken['output']['send_reason']);
        $this->assertNotEmpty($broken['output']['text']);
    }

    public function testCleanViewText(): void
    {
        foreach ([['ver 2', '2'], ['Info de la camiseta', 'camiseta'], ['ver el producto 3', '3'], ['foto 1', '1'], ['detalles del vaso', 'vaso'], ['2', '2'], ['Camiseta negra', 'Camiseta negra']] as [$in, $out]) {
            $this->assertSame($out, ProductNodes::cleanViewText($in), $in);
        }
    }

    public function testNodeIsDeclaredForTheEditor(): void
    {
        $def = ProductNodes::definitions()['shop.product'];

        $this->assertSame(['found', 'choose', 'not_found'], array_column($def['handles'], 'id'));
        $this->assertContains('send', array_column($def['fields'], 'key'));
    }
}
