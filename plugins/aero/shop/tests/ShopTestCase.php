<?php namespace Aero\Shop\Tests;

use Illuminate\Support\Facades\DB;
use PluginTestCase;

/**
 * Entorno común de los tests de shop: SQLite en memoria con las migraciones
 * propias de la tienda, sin arrancar plugins ajenos (ver nota abajo).
 */
abstract class ShopTestCase extends PluginTestCase
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

        // Tabla mínima de usuarios (FK opcional de los clientes).
        \Schema::create('users', function ($t) {
            $t->id();
        });

        // Tabla mínima de tenants para satisfacer las claves foráneas de la tienda.
        \Schema::create('aero_sites_tenants', function ($t) {
            $t->id();
            $t->string('name')->nullable();
            $t->timestamp('deleted_at')->nullable();
        });
        DB::table('aero_sites_tenants')->insert([['id' => 1, 'name' => 'Uno'], ['id' => 2, 'name' => 'Dos']]);

        // Dominio principal: lo usa OrderService::publicUrl para el enlace de seguimiento.
        \Schema::create('aero_sites_domains', function ($t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->string('domain');
            $t->boolean('is_primary')->default(false);
        });
        DB::table('aero_sites_domains')->insert([
            ['tenant_id' => 1, 'domain' => 'uno.test', 'is_primary' => 1],
            ['tenant_id' => 2, 'domain' => 'dos.test', 'is_primary' => 1],
        ]);

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

    protected function product(int $tenant, ?int $collection, string $name, float $price = 10, string $status = 'active', bool $internal = false, array $extra = []): int
    {
        return DB::table('aero_shop_products')->insertGetId($extra + [
            'tenant_id' => $tenant, 'collection_id' => $collection, 'type' => 'physical', 'name' => $name,
            'slug' => \Str::slug($name) . '-' . uniqid(), 'base_price' => $price, 'status' => $status,
            'is_internal' => $internal, 'track_inventory' => false, 'stock_quantity' => 0, 'has_variants' => false,
            'requires_shipping' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function collection(int $tenant, string $name, int $sort = 0, ?int $parent = null, bool $active = true): int
    {
        return DB::table('aero_shop_collections')->insertGetId([
            'tenant_id' => $tenant, 'parent_id' => $parent, 'name' => $name, 'slug' => \Str::slug($name),
            'is_active' => $active, 'sort_order' => $sort, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Moneda Bs y tienda activa para el tenant (lo que exige OrderService). */
    protected function openStore(int $tenant, array $settings = []): void
    {
        $currencyId = DB::table('aero_shop_currencies')->where('code', 'BOB')->value('id')
            ?: DB::table('aero_shop_currencies')->insertGetId(['code' => 'BOB', 'name' => 'Boliviano', 'symbol' => 'Bs', 'decimal_places' => 2, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);

        DB::table('aero_shop_settings')->insert($settings + [
            'tenant_id' => $tenant, 'is_enabled' => 1, 'base_currency_id' => $currencyId, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Tablas mínimas de Hello (contactos, identidades y mensajes) para probar la lectura del chat. */
    protected function helloTables(): void
    {
        \Schema::create('aero_hello_contacts', function ($t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id')->nullable();
            $t->string('name')->nullable();
            $t->timestamps();
        });
        \Schema::create('aero_hello_contact_identities', function ($t) {
            $t->id();
            $t->unsignedBigInteger('contact_id');
            $t->string('platform');
            $t->string('external_id');
            $t->timestamps();
        });
        \Schema::create('aero_hello_messages', function ($t) {
            $t->id();
            $t->unsignedBigInteger('contact_id');
            $t->unsignedBigInteger('account_id')->nullable();
            $t->string('direction');
            $t->string('type')->default('text');
            $t->text('body')->nullable();
            $t->timestamps();
        });
    }

    /** Tablas mínimas de Hello (contactos, identidades y mensajes) para probar la lectura del chat. */}
