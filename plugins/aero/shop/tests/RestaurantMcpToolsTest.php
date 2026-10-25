<?php namespace Aero\Shop\Tests;

use Aero\Shop\Classes\Mcp\RestaurantTools;
use Aero\Shop\Classes\OrderService;
use Aero\Shop\Classes\PanicService;
use Aero\Shop\Models\Order;
use Illuminate\Support\Facades\DB;

require_once __DIR__ . '/ShopTestCase.php';

/** Tools de MCP del restaurante: forma de las definiciones, alertas, búsqueda por número del día y aislamiento por tenant. */
class RestaurantMcpToolsTest extends ShopTestCase
{
    protected int $plato;

    public function setUp(): void
    {
        parent::setUp();
        $this->openStore(1, ['store_mode' => 'restaurant']);
        $this->openStore(2, ['store_mode' => 'restaurant']);
        $this->plato = $this->product(1, null, 'Plato del día', 35);
        $this->product(2, null, 'Plato del otro', 20);
    }

    protected function pedido(string $type = 'dine_in', ?string $table = '4', string $name = 'Ana', int $tenant = 1): Order
    {
        $product = DB::table('aero_shop_products')->where('tenant_id', $tenant)->value('id');

        return (new OrderService())->create($tenant, [['product_id' => $product, 'quantity' => 2]],
            ['first_name' => $name, 'email' => strtolower($name) . '@test.bo'], null,
            ['source' => 'pos', 'counter' => true, 'order_type' => $type, 'table_label' => $table]);
    }

    public function testToolDefinitionsAreReadOnlyAndCallable(): void
    {
        $defs = RestaurantTools::definitions();

        foreach (['restaurant_overview', 'restaurant_alerts', 'kitchen_board', 'restaurant_orders'] as $name) {
            $this->assertArrayHasKey($name, $defs);
            $this->assertFalse($defs[$name]['write'], "$name no debe escribir");
            $this->assertSame('shop', $defs[$name]['module']);
            $this->assertTrue(is_callable($defs[$name]['handler']), "$name debe tener handler");
            $this->assertNotSame('', $defs[$name]['description']);
        }
    }

    public function testEmergencyIsTheFirstAlertAndIsScopedToItsTenant(): void
    {
        $o = $this->pedido('dine_in', '7');
        [$alert, $result] = PanicService::raise($o, '10.0.0.1');
        $this->assertSame('created', $result);

        $alerts = RestaurantTools::alerts([], 1);
        $this->assertSame('critica', $alerts['alerts'][0]['prioridad']);
        $this->assertSame('emergencia', $alerts['alerts'][0]['tipo']);
        $this->assertStringContainsString('Mesa 7', $alerts['alerts'][0]['mensaje']);
        $this->assertStringContainsString('Mesa 7', $alerts['resumen']);

        // Otro negocio no ve la emergencia de este.
        $this->assertSame([], RestaurantTools::overview([], 2)['emergencias_activas']);
        $this->assertStringContainsString('Todo en orden', RestaurantTools::alerts([], 2)['resumen']);

        // Atendida: deja de ser alerta.
        PanicService::acknowledge(1, $alert->id, 'prueba');
        $this->assertSame([], RestaurantTools::overview([], 1)['emergencias_activas']);
    }

    public function testNewOrderWaitingTooLongRaisesAnAlert(): void
    {
        $o = $this->pedido();
        DB::table('aero_shop_orders')->where('id', $o->id)->update(['created_at' => now()->subMinutes(12)]);

        $types = array_column(RestaurantTools::alerts([], 1)['alerts'], 'tipo');
        $this->assertContains('pedido_nuevo_sin_aceptar', $types);
        // Con un umbral mayor no avisa.
        $this->assertNotContains('pedido_nuevo_sin_aceptar', array_column(RestaurantTools::alerts(['new_wait_minutes' => 30], 1)['alerts'], 'tipo'));
    }

    public function testOrdersAreFoundByDailyNumberTableAndTenant(): void
    {
        $a = $this->pedido('dine_in', '4', 'Ana');
        $b = $this->pedido('pickup', null, 'Beto');
        $ajeno = $this->pedido('dine_in', '4', 'Zoe', 2);

        $this->assertNotNull($a->fresh()->daily_number, 'los pedidos de restaurante reciben número del día');

        $byNumber = RestaurantTools::orders(['number' => (string) $b->fresh()->daily_number], 1);
        $this->assertSame(1, $byNumber['total']);
        $this->assertSame('Beto', $byNumber['pedidos'][0]['cliente']);

        $byTable = RestaurantTools::orders(['table' => '4'], 1);
        $this->assertSame(1, $byTable['total'], 'la mesa 4 del otro tenant no cuenta');
        $this->assertSame('Ana', $byTable['pedidos'][0]['cliente']);
        $this->assertNotSame('Zoe', $byTable['pedidos'][0]['cliente']);
        $this->assertNotNull($ajeno->id);

        $board = RestaurantTools::kitchenBoard([], 1);
        $this->assertSame(2, $board['total']);
        $this->assertArrayHasKey('new', $board['columnas']);
    }

    public function testNonRestaurantStoreReturnsAClearError(): void
    {
        DB::table('aero_shop_settings')->where('tenant_id', 2)->update(['store_mode' => 'standard']);

        $this->assertStringContainsString('modo Restaurante', RestaurantTools::overview([], 2)['error']);
        $this->assertStringContainsString('no está activada', RestaurantTools::alerts([], 999)['error']);
    }
}
