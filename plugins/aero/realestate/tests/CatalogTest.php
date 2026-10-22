<?php namespace Aero\Realestate\Tests;

use Aero\Realestate\Models\Agent;
use Aero\Realestate\Models\Client;
use Aero\Realestate\Models\Location;
use Aero\Realestate\Models\Offer;
use Aero\Realestate\Models\Owner;
use Aero\Realestate\Models\Property;
use PluginTestCase;

class CatalogTest extends PluginTestCase
{
    public function testMigrationsCreateTables(): void
    {
        foreach (['locations', 'features', 'agents', 'owners', 'clients', 'properties', 'offers', 'feature_property'] as $t) {
            $this->assertTrue(\Schema::hasTable('aero_realestate_' . $t), "Falta aero_realestate_{$t}");
        }
    }

    public function testNavigationRequiresDeclaredPermissions(): void
    {
        $plugin = new \Aero\Realestate\Plugin($this->app);
        $declared = array_keys($plugin->registerPermissions());
        foreach ($plugin->registerNavigation() as $item) {
            foreach (($item['permissions'] ?? []) as $p) {
                $this->assertContains($p, $declared);
            }
        }
    }

    public function testPropertyGetsCodeSlugAndLocationChain(): void
    {
        $dep = Location::create(['type' => 'department', 'name' => 'Santa Cruz']);
        $city = Location::create(['type' => 'city', 'name' => 'Santa Cruz de la Sierra', 'parent_id' => $dep->id]);
        $zone = Location::create(['type' => 'zone', 'name' => 'Equipetrol', 'parent_id' => $city->id]);

        $p = Property::create(['tenant_id' => 1, 'title' => 'Casa en Equipetrol', 'type' => 'house', 'status' => 'available', 'location_id' => $zone->id]);

        $this->assertSame('INM-' . str_pad((string) $p->id, 5, '0', STR_PAD_LEFT), $p->fresh()->code);
        $this->assertSame('casa-en-equipetrol-' . $p->id, $p->fresh()->slug);
        $this->assertSame(['Santa Cruz', 'Santa Cruz de la Sierra', 'Equipetrol'], [$p->department, $p->city, $p->zone]);
    }

    public function testPropertyHasIndependentOffersPerModality(): void
    {
        $p = Property::create(['tenant_id' => 1, 'title' => 'Depto', 'type' => 'apartment', 'status' => 'available']);
        $sale = Offer::create(['property_id' => $p->id, 'modality' => 'sale', 'amount' => 90000, 'currency' => 'USD', 'term_months' => 12]);
        $anti = Offer::create(['property_id' => $p->id, 'modality' => 'anticretico', 'amount' => 25000, 'currency' => 'USD', 'term_months' => 24]);

        $this->assertSame(1, $sale->tenant_id);          // hereda el tenant de la propiedad
        $this->assertNull($sale->fresh()->term_months);  // la venta no tiene plazo
        $anti->update(['status' => 'closed']);
        $this->assertNotNull($anti->fresh()->closed_at);
        $this->assertSame('available', $sale->fresh()->status); // cerrar una no toca la otra
        $this->assertSame(2, $p->offers()->count());
    }

    public function testTenantIsolationFailsClosed(): void
    {
        Owner::create(['tenant_id' => 1, 'name' => 'Ana']);
        Owner::create(['tenant_id' => 2, 'name' => 'Beto']);
        Client::create(['tenant_id' => 1, 'name' => 'Cris']);

        $this->assertSame(['Ana'], Owner::forTenant(1)->pluck('name')->all());
        $this->assertSame(['Beto'], Owner::forTenant(2)->pluck('name')->all());
        $this->assertSame(0, Owner::forTenant(null)->count());   // sin tenant no ve nada
        $this->assertSame(0, Client::forTenant(2)->count());
    }

    public function testPersonUserIsCreatedAndLinkedOnce(): void
    {
        $service = new \Aero\Realestate\Classes\PersonUsers();
        $a = Client::create(['tenant_id' => 1, 'name' => 'Luis Pérez', 'email' => 'luis@example.com']);
        $user = $service->ensureUser($a);

        $this->assertTrue($service->created);
        $this->assertSame($user->id, $a->fresh()->user_id);
        $this->assertSame('Luis', $user->first_name);

        // el mismo usuario en otro rol/tabla se reutiliza, no se duplica
        $o = Owner::create(['tenant_id' => 1, 'name' => 'Luis Pérez', 'email' => 'luis@example.com']);
        $this->assertSame($user->id, $service->ensureUser($o)->id);
        $this->assertFalse($service->created);

        // otro cliente del mismo tenant no puede tomar ese usuario
        $b = Client::create(['tenant_id' => 1, 'name' => 'Otro', 'email' => 'luis@example.com']);
        $this->expectException(\Aero\Realestate\Classes\RealestateException::class);
        $service->ensureUser($b);
    }
}
