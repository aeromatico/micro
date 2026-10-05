<?php namespace Aero\Shop\Tests;

use Aero\Shop\Classes\Workflows\CatalogNodes;
use Illuminate\Support\Facades\DB;
use PluginTestCase;

class CatalogNodesTest extends PluginTestCase
{
    /**
     * Shop requiere Aero.Sites (y éste a RainLab.User): arrancarlos en el
     * harness exige tablas ajenas. Los nodos solo necesitan las tablas de la
     * tienda, así que no se registra ningún plugin: se corren las migraciones
     * propias de shop (salvo seeds de demo) sobre SQLite en memoria.
     */
    protected $autoRegister = false;

    /** Migran los módulos de October (system_settings, etc.) pero no los plugins. */
    protected function migrateCurrentPlugin()
    {
    }

    public function setUp(): void
    {
        parent::setUp();

        // Tabla mínima de tenants para satisfacer las claves foráneas de la tienda.
        \Schema::create('aero_sites_tenants', function ($t) {
            $t->id();
            $t->string('name')->nullable();
            $t->timestamp('deleted_at')->nullable();
        });
        DB::table('aero_sites_tenants')->insert([['id' => 1, 'name' => 'Uno'], ['id' => 2, 'name' => 'Dos']]);

        $versions = \Symfony\Component\Yaml\Yaml::parseFile(__DIR__ . '/../updates/version.yaml');

        foreach ($versions as $entries) {
            foreach ((array) $entries as $entry) {
                if (!is_string($entry) || !str_ends_with($entry, '.php') || preg_match('/^(seed|attach)_/', $entry)) {
                    continue;
                }

                $migration = require __DIR__ . '/../updates/' . $entry;
                $migration->up();
            }
        }
    }

    protected function collection(int $tenant, string $name, int $sort = 0, ?int $parent = null, bool $active = true): int
    {
        return DB::table('aero_shop_collections')->insertGetId([
            'tenant_id' => $tenant, 'parent_id' => $parent, 'name' => $name, 'slug' => \Str::slug($name),
            'is_active' => $active, 'sort_order' => $sort, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function product(int $tenant, ?int $collection, string $name, float $price = 10, string $status = 'active', bool $internal = false): int
    {
        return DB::table('aero_shop_products')->insertGetId([
            'tenant_id' => $tenant, 'collection_id' => $collection, 'type' => 'physical', 'name' => $name,
            'slug' => \Str::slug($name) . '-' . uniqid(), 'base_price' => $price, 'status' => $status,
            'is_internal' => $internal, 'track_inventory' => false, 'stock_quantity' => 0, 'has_variants' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function seedStore(): array
    {
        $entradas = $this->collection(1, 'Entradas', 1);
        $bebidas = $this->collection(1, 'Bebidas', 2);
        $vacia = $this->collection(1, 'Vacía', 3);
        $this->collection(1, 'Oculta', 4, null, false);
        $this->collection(2, 'Postres de otro tenant', 1);

        $this->product(1, $entradas, 'Empanada', 10);
        $this->product(1, $entradas, 'Salteña', 12);
        $this->product(1, $bebidas, 'Café', 8);
        $this->product(1, $bebidas, 'Borrador', 9, 'draft');
        $this->product(1, $bebidas, 'Interno POS', 9, 'active', true);

        return compact('entradas', 'bebidas', 'vacia');
    }

    public function testMenuIsNumberedAndSkipsEmptyHiddenAndForeign(): void
    {
        $this->seedStore();

        $out = CatalogNodes::categories([], [], 1);

        $this->assertSame('found', $out['handle']);
        $this->assertSame(['Entradas', 'Bebidas'], array_column($out['output']['items'], 'name'));
        $this->assertSame([1, 2], array_column($out['output']['items'], 'n'));
        $this->assertSame([2, 1], array_column($out['output']['items'], 'products_count'));
        $this->assertStringContainsString("1. Entradas\n2. Bebidas", $out['output']['text']);
        $this->assertSame('categorias', $out['var']);
    }

    public function testMenuOptions(): void
    {
        $this->seedStore();

        $withEmpty = CatalogNodes::categories(['include_empty' => '1'], [], 1);
        $this->assertCount(3, $withEmpty['output']['items']);

        $max = CatalogNodes::categories(['max_items' => 1, 'title' => 'Menú', 'footer' => 'Elige', 'save_as' => 'menu'], [], 1);
        $this->assertCount(1, $max['output']['items']);
        $this->assertStringStartsWith('*Menú*', $max['output']['text']);
        $this->assertSame('menu', $max['var']);

        $this->assertSame('empty', CatalogNodes::categories([], [], 99)['handle']);
    }

    public function testProductsResolveNumberNameAndSlug(): void
    {
        $this->seedStore();

        foreach (['2', '2.', ' bebidas ', 'BEBIDAS', 'beb'] as $choice) {
            $out = CatalogNodes::products(['category' => $choice], [], 1);
            $this->assertSame('found', $out['handle'], "No entendió: {$choice}");
            $this->assertSame('Bebidas', $out['output']['category']['name']);
        }

        // Solo el producto activo y público: sin borradores ni internos del POS.
        $out = CatalogNodes::products(['category' => '2'], [], 1);
        $this->assertSame(['Café'], array_column($out['output']['items'], 'name'));
        $this->assertStringContainsString('1. Café — Bs 8.00', $out['output']['text']);
    }

    public function testProductsReadTheCustomerReplyWhenCategoryIsEmpty(): void
    {
        $this->seedStore();

        $out = CatalogNodes::products([], ['trigger' => ['data' => [['body' => '1']]]], 1);
        $this->assertSame('Entradas', $out['output']['category']['name']);
        $this->assertCount(2, $out['output']['items']);
    }

    public function testProductsNotFoundAndEmptyBranches(): void
    {
        $this->seedStore();

        $bad = CatalogNodes::products(['category' => 'zzz'], [], 1);
        $this->assertSame('not_found', $bad['handle']);
        $this->assertStringContainsString('1. Entradas', $bad['output']['menu_text']);

        $this->assertSame('not_found', CatalogNodes::products(['category' => '9'], [], 1)['handle']);
        $this->assertSame('not_found', CatalogNodes::products(['category' => ''], [], 1)['handle']);

        // La categoría vacía no está en el menú, pero por nombre se encuentra y sale por «empty».
        $this->assertSame('empty', CatalogNodes::products(['category' => 'Vacía'], [], 1)['handle']);
    }

    public function testProductsUseTheMenuOptionsOfTheLinkedMenuNode(): void
    {
        $this->seedStore();

        // El menú del flujo incluye categorías vacías: ahora «3» es «Vacía» y no queda fuera.
        $run = $this->fakeRun([
            'nodes' => [
                ['id' => 'm', 'type' => 'shop.categories', 'data' => ['include_empty' => '1']],
                ['id' => 'p', 'type' => 'shop.products', 'data' => []],
            ],
            'edges' => [['source' => 'm', 'target' => 'p']],
        ]);

        $out = CatalogNodes::products(['category' => '3'], [], 1, $run, ['id' => 'p']);
        $this->assertSame('empty', $out['handle']);
        $this->assertSame('Vacía', $out['output']['category']['name']);

        // Sin nodo de menú en el flujo, «3» no existe (opciones por defecto).
        $plain = $this->fakeRun(['nodes' => [['id' => 'p', 'type' => 'shop.products']], 'edges' => []]);
        $this->assertSame('not_found', CatalogNodes::products(['category' => '3'], [], 1, $plain, ['id' => 'p'])['handle']);
    }

    public function testSameRunReusesTheMenuAlreadyBuilt(): void
    {
        $this->seedStore();

        // El menú de esta ejecución dice que «1» es Bebidas (otro orden): se respeta.
        $ctx = ['vars' => ['categorias' => ['items' => [['n' => 1, 'id' => DB::table('aero_shop_collections')->where('name', 'Bebidas')->value('id')]]]]];

        $this->assertSame('Bebidas', CatalogNodes::products(['category' => '1'], $ctx, 1)['output']['category']['name']);
    }

    public function testTenantIsolation(): void
    {
        $this->seedStore();

        $this->assertSame('not_found', CatalogNodes::products(['category' => 'Postres de otro tenant'], [], 1)['handle']);
        // Para su dueño sí existe (sin productos: sale por «empty»).
        $this->assertSame('empty', CatalogNodes::products(['category' => 'Postres de otro tenant'], [], 2)['handle']);

        // Un category_id de otro tenant tampoco resuelve.
        $foreign = DB::table('aero_shop_collections')->where('tenant_id', 2)->value('id');
        $this->assertSame('not_found', CatalogNodes::products(['category_id' => $foreign], [], 1)['handle']);

        $this->expectException(\RuntimeException::class);
        CatalogNodes::categories([], [], null);
    }

    public function testNodesAreDeclaredForTheEditor(): void
    {
        $defs = CatalogNodes::definitions();

        $this->assertSame(['found', 'empty'], array_column($defs['shop.categories']['handles'], 'id'));
        $this->assertSame(['found', 'empty', 'not_found'], array_column($defs['shop.products']['handles'], 'id'));
        $this->assertNotEmpty($defs['shop.products']['fields']);
    }

    protected function fakeRun(array $graph): object
    {
        $workflow = new class($graph) {
            public function __construct(protected array $graph) {}

            public function jsonField(string $field): array
            {
                return $field === 'graph' ? $this->graph : [];
            }
        };

        return (object) ['workflow' => $workflow];
    }
}
