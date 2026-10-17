<?php namespace Aero\Shop\Tests;

use Aero\Shop\Classes\Exceptions\OrderException;
use Aero\Shop\Classes\OrderService;
use Aero\Shop\Classes\SellerBalance;
use Aero\Shop\Models\Order;
use Aero\Shop\Models\Seller;
use Illuminate\Support\Facades\DB;

require_once __DIR__ . '/ShopTestCase.php';

class OrderServiceMarketplaceTest extends ShopTestCase
{
    protected array $ids = [];

    protected function seller(int $tenant, int $userId, string $type, float $value, bool $active = true): Seller
    {
        return Seller::create([
            'tenant_id' => $tenant, 'backend_user_id' => $userId, 'display_name' => 'Vendedor ' . $userId,
            'commission_type' => $type, 'commission_value' => $value, 'is_active' => $active,
        ]);
    }

    protected function place(array $products, int $tenant = 1): Order
    {
        $items = array_map(fn ($id) => ['product_id' => $id, 'quantity' => 1], $products);

        return (new OrderService())->create($tenant, $items, ['first_name' => 'Ana', 'email' => 'ana@test.bo']);
    }

    protected function buyMarketplaceService(int $tenant): void
    {
        $schema = \Illuminate\Support\Facades\Schema::class;
        if (!$schema::hasTable('aero_services_services')) {
            $schema::create('aero_services_services', function ($t) { $t->increments('id'); $t->string('slug'); });
            $schema::create('aero_services_purchases', function ($t) {
                $t->increments('id'); $t->unsignedInteger('tenant_id'); $t->unsignedInteger('service_id');
                $t->string('status')->default('pending'); $t->timestamps();
            });
        }
        $sid = DB::table('aero_services_services')->insertGetId(['slug' => 'marketplace']);
        DB::table('aero_services_purchases')->insert(['tenant_id' => $tenant, 'service_id' => $sid, 'status' => 'fulfilled']);
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->openStore(1, ['store_mode' => 'marketplace']);
        $this->buyMarketplaceService(1);
        $a = $this->seller(1, 10, 'percent', 10);
        $b = $this->seller(1, 11, 'fixed', 5);
        $this->ids = [
            'a' => $a->id, 'b' => $b->id,
            'pa' => $this->product(1, null, 'Producto A', 100, extra: ['seller_id' => $a->id]),
            'pb' => $this->product(1, null, 'Producto B', 40, extra: ['seller_id' => $b->id]),
            'propio' => $this->product(1, null, 'Producto tienda', 20),
        ];
    }

    public function testCartIsSplitOneOrderPerSeller(): void
    {
        $first = $this->place([$this->ids['pa'], $this->ids['pb'], $this->ids['propio']]);

        $orders = Order::where('order_group', $first->order_group)->get();
        $this->assertNotNull($first->order_group);
        $this->assertCount(3, $orders);
        $this->assertSame([$this->ids['a'], $this->ids['b'], null], $orders->pluck('seller_id')->all());
        $this->assertCount(3, $orders->pluck('order_number')->unique());
    }

    public function testPercentAndFixedCommission(): void
    {
        $first = $this->place([$this->ids['pa'], $this->ids['pb'], $this->ids['propio']]);
        $bySeller = Order::where('order_group', $first->order_group)->get()->keyBy('seller_id');

        $this->assertEquals(10.0, $bySeller[$this->ids['a']]->commission_amount);
        $this->assertEquals(90.0, $bySeller[$this->ids['a']]->seller_net);
        $this->assertEquals(5.0, $bySeller[$this->ids['b']]->commission_amount);
        $this->assertEquals(35.0, $bySeller[$this->ids['b']]->seller_net);
        $this->assertEquals(0.0, $bySeller['']->commission_amount);
        $this->assertNull($bySeller['']->seller_net);
    }

    public function testFixedCommissionNeverExceedsTheAmount(): void
    {
        $this->assertEquals(3.0, Seller::find($this->ids['b'])->commissionFor(3.0));
    }

    public function testInactiveSellerProductsCannotBeBought(): void
    {
        Seller::find($this->ids['a'])->update(['is_active' => false]);

        $this->expectException(OrderException::class);
        $this->place([$this->ids['pa']]);
    }

    public function testStandardStoreDoesNotSplit(): void
    {
        $this->openStore(2, ['store_mode' => 'standard']);
        $p1 = $this->product(2, null, 'Uno', 10);
        $p2 = $this->product(2, null, 'Dos', 10);

        $order = $this->place([$p1, $p2], 2);

        $this->assertNull($order->order_group);
        $this->assertNull($order->seller_id);
        $this->assertSame(1, Order::where('tenant_id', 2)->count());
    }

    public function testBalanceCountsOnlyPaidOrdersOfThatSeller(): void
    {
        $service = new OrderService();
        $first = $this->place([$this->ids['pa'], $this->ids['pb']]);
        $orders = Order::where('order_group', $first->order_group)->get()->keyBy('seller_id');

        $service->markPaid($orders[$this->ids['a']]);

        $balance = SellerBalance::for(1, $this->ids['a']);
        $this->assertEquals(100.0, $balance['collected']);
        $this->assertEquals(10.0, $balance['commission']);
        $this->assertEquals(90.0, $balance['net']);
        $this->assertEquals(0.0, $balance['pending']);

        $other = SellerBalance::for(1, $this->ids['b']);
        $this->assertEquals(0.0, $other['collected']);
        $this->assertEquals(40.0, $other['pending']);
    }

    public function testRefundedOrderLeavesTheNetBalance(): void
    {
        $service = new OrderService();
        $order = Order::where('order_group', $this->place([$this->ids['pa']])->order_group)->first();
        $service->markPaid($order);
        $service->void($order->fresh(), 'devolución');

        $balance = SellerBalance::for(1, $this->ids['a']);
        $this->assertEquals(0.0, $balance['net']);
        $this->assertEquals(100.0, $balance['refunded']);
    }

    public function testOtherTenantSellerIsNeverReachable(): void
    {
        $foreign = $this->seller(2, 99, 'percent', 0);
        $this->openStore(2, ['store_mode' => 'marketplace']);
        // Producto del tenant 1 apuntando a un comerciante del tenant 2: se rechaza.
        $bad = $this->product(1, null, 'Cruzado', 10, extra: ['seller_id' => $foreign->id]);

        $this->expectException(OrderException::class);
        $this->place([$bad]);
    }
}
