<?php namespace Aero\Realestate\Tests;

use Aero\Realestate\Classes\DealService;
use Aero\Realestate\Classes\LeadService;
use Aero\Realestate\Classes\RealestateException;
use Aero\Realestate\Classes\VisitService;
use Aero\Realestate\Models\Agent;
use Aero\Realestate\Models\Client;
use Aero\Realestate\Models\Commission;
use Aero\Realestate\Models\CommissionRule;
use Aero\Realestate\Models\Interaction;
use Aero\Realestate\Models\Lead;
use Aero\Realestate\Models\Offer;
use Aero\Realestate\Models\Property;
use Aero\Realestate\Models\Visit;
use PluginTestCase;

class CrmTest extends PluginTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        \Aero\Realestate\Classes\Notifier::$muted = true;
    }

    protected function world(int $tenant = 1): array
    {
        $agent = Agent::create(['tenant_id' => $tenant, 'name' => 'Carlos', 'phone' => '71111111']);
        $client = Client::create(['tenant_id' => $tenant, 'name' => 'Luis', 'phone' => '70000000']);
        $p = Property::create(['tenant_id' => $tenant, 'title' => 'Casa', 'type' => 'house', 'agent_id' => $agent->id]);
        $sale = Offer::create(['property_id' => $p->id, 'modality' => 'sale', 'amount' => 100000, 'currency' => 'USD']);
        $lead = Lead::create(['client_id' => $client->id, 'property_id' => $p->id, 'offer_id' => $sale->id]);

        return [$agent, $client, $p, $sale, $lead];
    }

    public function testLeadStartsNewInheritsAgentAndModalityAndLogs(): void
    {
        [$agent, , , , $lead] = $this->world();
        $this->assertSame('new', $lead->stage);
        $this->assertSame($agent->id, $lead->agent_id);
        $this->assertSame('sale', $lead->modality);
        $this->assertSame(1, $lead->tenant_id);
        $this->assertSame(1, Interaction::where('lead_id', $lead->id)->count());
    }

    public function testStageMovesAndLostNeedsReason(): void
    {
        [, , , , $lead] = $this->world();
        $svc = app(LeadService::class);

        $svc->moveTo($lead, 'contacted');
        $this->assertSame('contacted', $lead->fresh()->stage);
        $this->assertNotNull($lead->fresh()->next_follow_up_at);   // agenda seguimiento solo

        try {
            $svc->moveTo($lead->fresh(), 'closed_lost');
            $this->fail('Debió exigir motivo');
        } catch (RealestateException $e) {
            $this->assertStringContainsString('por qué', $e->getMessage());
        }
        $svc->moveTo($lead->fresh(), 'closed_lost', 'Compró otra');
        $this->assertSame('closed_lost', $lead->fresh()->stage);
        $this->assertNull($lead->fresh()->next_follow_up_at);

        $this->expectException(RealestateException::class);
        $svc->moveTo($lead->fresh(), 'closed_won');   // el cierre ganado solo va por DealService
    }

    public function testContactLogResetsFollowUp(): void
    {
        [, , , , $lead] = $this->world();
        app(LeadService::class)->logContact($lead, 'call', 'Quiere ver el jueves', now()->addDays(2)->toDateTimeString());
        $lead = $lead->fresh();
        $this->assertSame('contacted', $lead->stage);
        $this->assertNull($lead->follow_up_alerted_at);
        $this->assertSame(now()->addDays(2)->toDateString(), $lead->next_follow_up_at->toDateString());
    }

    public function testVisitBlocksAgentOverlapAndAdvancesLead(): void
    {
        [$agent, $client, $p, , $lead] = $this->world();
        $svc = app(VisitService::class);
        $when = now()->addDays(2)->setTime(10, 0);

        $v = $svc->schedule(['property_id' => $p->id, 'client_id' => $client->id, 'starts_at' => $when, 'lead_id' => $lead->id]);
        $this->assertSame('confirmed', $v->status);
        $this->assertSame($agent->id, $v->agent_id);
        $this->assertSame('visit_scheduled', $lead->fresh()->stage);
        $this->assertSame(45, (int) $v->starts_at->diffInMinutes($v->ends_at, true));

        try {
            $svc->schedule(['property_id' => $p->id, 'client_id' => $client->id, 'starts_at' => $when->copy()->addMinutes(20)]);
            $this->fail('Debió chocar con la agenda del agente');
        } catch (RealestateException $e) {
            $this->assertStringContainsString($v->code, $e->getMessage());
        }
        $this->assertSame(1, Visit::count());   // la que chocó no deja residuo
    }

    public function testPortalVisitNeedsConfirmationAndLifecycle(): void
    {
        [, $client, $p] = $this->world();
        $svc = app(VisitService::class);
        $v = $svc->schedule(['property_id' => $p->id, 'client_id' => $client->id, 'starts_at' => now()->addDays(3)->setTime(15, 0)], 'portal');
        $this->assertSame('requested', $v->status);

        $svc->confirm($v);
        $svc->reschedule($v->fresh(), now()->addDays(4)->setTime(9, 0), 'Lluvia');
        $this->assertSame('rescheduled', $v->fresh()->status);
        $svc->complete($v->fresh(), 'Le gustó');
        $this->assertSame('done', $v->fresh()->status);

        $this->expectException(RealestateException::class);
        $svc->cancel($v->fresh());   // una visita realizada no se cancela
    }

    public function testCloseSaleSetsPropertySoldAndComputesCommission(): void
    {
        [$agent, , $p, $sale, $lead] = $this->world();
        CommissionRule::create(['tenant_id' => 1, 'modality' => 'sale', 'basis' => 'percent', 'value' => 3, 'agent_share_percent' => 40]);

        app(DealService::class)->close($lead, $sale, 95000);

        $this->assertSame('closed_won', $lead->fresh()->stage);
        $this->assertSame('sold', $p->fresh()->status);
        $this->assertSame('closed', $sale->fresh()->status);
        $c = Commission::where('lead_id', $lead->id)->first();
        $this->assertEquals(2850, (float) $c->commission_amount);     // 3 % de 95.000
        $this->assertEquals(1140, (float) $c->agent_amount);          // 40 % para el agente
        $this->assertSame($agent->id, $c->agent_id);
        $this->assertSame('pending', $c->status);
    }

    public function testAgentRuleOverridesGeneralAndRentUsesMonthlyRents(): void
    {
        [$agent, $client, $p, , ] = $this->world();
        $rent = Offer::create(['property_id' => $p->id, 'modality' => 'rent', 'amount' => 500, 'currency' => 'USD']);
        $lead = Lead::create(['client_id' => $client->id, 'property_id' => $p->id, 'offer_id' => $rent->id]);
        CommissionRule::create(['tenant_id' => 1, 'modality' => 'rent', 'basis' => 'monthly_rents', 'value' => 1, 'agent_share_percent' => 50]);
        CommissionRule::create(['tenant_id' => 1, 'modality' => 'rent', 'agent_id' => $agent->id, 'basis' => 'monthly_rents', 'value' => 0.5, 'agent_share_percent' => 80]);

        app(DealService::class)->close($lead, $rent);
        $c = Commission::where('lead_id', $lead->id)->first();

        $this->assertEquals(250, (float) $c->commission_amount);      // media renta: gana la regla del agente
        $this->assertEquals(200, (float) $c->agent_amount);
        $this->assertSame('rented', $p->fresh()->status);
    }

    public function testAnticreticoDealDoesNotTouchPropertyAndNoRuleMeansNoCommission(): void
    {
        [, $client, $p] = $this->world();
        $anti = Offer::create(['property_id' => $p->id, 'modality' => 'anticretico', 'amount' => 20000, 'currency' => 'USD', 'term_months' => 24]);
        $lead = Lead::create(['client_id' => $client->id, 'property_id' => $p->id, 'offer_id' => $anti->id]);

        app(DealService::class)->close($lead, $anti);

        $this->assertSame('available', $p->fresh()->status);   // lo moverá el contrato al activarse
        $this->assertSame('available', $anti->fresh()->status);
        $this->assertSame(0, Commission::count());
    }

    public function testCannotCloseWithOfferFromAnotherTenantOrProperty(): void
    {
        [, , , , $lead] = $this->world(1);
        [, , , $foreign] = $this->world(2);
        $this->expectException(RealestateException::class);
        app(DealService::class)->close($lead, $foreign);
    }

    public function testMonthlyRentsRuleOnlyForRent(): void
    {
        $this->expectException(\ApplicationException::class);
        CommissionRule::create(['tenant_id' => 1, 'modality' => 'sale', 'basis' => 'monthly_rents', 'value' => 1, 'agent_share_percent' => 50]);
    }

    public function testTenantIsolation(): void
    {
        $this->world(1);
        $this->world(2);
        $this->assertSame(1, Lead::forTenant(1)->count());
        $this->assertSame(0, Lead::forTenant(null)->count());
        $this->assertSame(0, Visit::forTenant(2)->count());
    }

    public function testFollowUpDueScope(): void
    {
        [, , , , $lead] = $this->world();
        $lead->forceFill(['next_follow_up_at' => now()->subHour()])->save();
        $this->assertTrue($lead->fresh()->is_follow_up_due);
        $lead->forceFill(['next_follow_up_at' => now()->addDay()])->save();
        $this->assertFalse($lead->fresh()->is_follow_up_due);
    }
}
