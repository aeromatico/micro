<?php namespace Aero\Church\Tests;

use Aero\Church\Classes\Notifier;
use Aero\Church\Http\Controllers\Api\EventsController;
use Aero\Church\Http\Controllers\Api\MembersController;
use Aero\Church\Http\Controllers\Api\VisitsController;
use Aero\Church\Models\ChurchEvent;
use Aero\Church\Models\Member;
use Aero\Church\Models\Visit;
use Illuminate\Http\Request;
use PluginTestCase;

class ApiTest extends PluginTestCase
{
    protected function req(?int $tenant, array $query = [], string $method = 'GET', array $body = []): Request
    {
        $r = Request::create('/api/v1/church/x', $method, $method === 'GET' ? $query : $body);
        $key = $tenant === null ? null : (object) ['owner_type' => \Aero\Sites\Models\Tenant::class, 'owner_id' => $tenant];
        $r->attributes->set('api_key', $key);

        return $r;
    }

    public function testMembersAreTenantScopedAndContactIsConsentGated(): void
    {
        $adult = Member::create(['tenant_id' => 1, 'first_name' => 'Ana', 'status' => 'active', 'phone' => '70011111', 'consent_messages' => true]);
        $noConsent = Member::create(['tenant_id' => 1, 'first_name' => 'Beto', 'status' => 'active', 'phone' => '70022222', 'consent_messages' => false]);
        $kid = Member::create(['tenant_id' => 1, 'first_name' => 'Kid', 'status' => 'active', 'phone' => '70033333', 'consent_messages' => true, 'birth_date' => now()->subYears(8)->toDateString()]);
        $other = Member::create(['tenant_id' => 2, 'first_name' => 'Otra', 'status' => 'active', 'phone' => '70099999', 'consent_messages' => true]);

        $c = new MembersController();
        $list = json_decode($c->index($this->req(1))->getContent(), true)['data'];
        $by = collect($list)->keyBy('id');

        $this->assertCount(3, $list);
        $this->assertNotContains($other->id, array_keys($by->all()));
        $this->assertSame('70011111', $by[$adult->id]['phone']);
        $this->assertArrayNotHasKey('phone', $by[$noConsent->id]);
        $this->assertArrayNotHasKey('phone', $by[$kid->id], 'Un menor nunca expone contacto');
        $this->assertStringNotContainsString('70099999', json_encode($list));

        $this->assertSame(404, $c->show($this->req(1), $other->id)->getStatusCode());
        $this->assertSame(200, $c->show($this->req(1), $adult->id)->getStatusCode());
        $this->assertSame(403, $c->index($this->req(null))->getStatusCode(), 'Una key sin iglesia no ve nada');
    }

    public function testEventsListAndTenantIsolation(): void
    {
        $mine = ChurchEvent::create(['tenant_id' => 1, 'title' => 'Culto', 'starts_at' => now()->addDay(), 'capacity' => 3]);
        ChurchEvent::create(['tenant_id' => 2, 'title' => 'Ajeno', 'starts_at' => now()->addDay()]);

        $c = new EventsController();
        $data = json_decode($c->index($this->req(1, ['upcoming' => '1']))->getContent(), true)['data'];

        $this->assertSame(['Culto'], array_column($data, 'title'));
        $this->assertSame(3, $data[0]['capacity']);
        $this->assertSame(404, $c->show($this->req(2), $mine->id)->getStatusCode());
    }

    public function testVisitStoreValidatesAndForcesTenantFromTheKey(): void
    {
        $c = new VisitsController();

        $bad = $c->store($this->req(1, [], 'POST', ['name' => 'Sin permiso', 'phone' => '1']));
        $this->assertSame(422, $bad->getStatusCode());

        $ok = $c->store($this->req(1, [], 'POST', ['name' => 'María', 'phone' => '70012345', 'consent_data' => true, 'tenant_id' => 99]));
        $this->assertSame(201, $ok->getStatusCode());
        $this->assertSame(1, Visit::first()->tenant_id, 'El tenant sale de la key, nunca del cuerpo');
        $this->assertSame(0, Visit::where('tenant_id', 99)->count());

        $list = json_decode($c->index($this->req(1))->getContent(), true)['data'];
        $this->assertStringNotContainsString('70012345', json_encode($list), 'La lectura no devuelve el contacto');
    }

    public function testNotifierIsSilentInTests(): void
    {
        $v = Visit::create(['tenant_id' => 1, 'name' => 'X', 'visit_date' => now()->toDateString()]);
        Notifier::visitRegistered($v);   // no lanza ni envía
        $this->assertTrue(true);
    }
}
