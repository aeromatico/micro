<?php namespace Aero\Realestate\Tests;

use Aero\Realestate\Classes\AnticreticoService;
use Aero\Realestate\Classes\Charts;
use Aero\Realestate\Classes\DealService;
use Aero\Realestate\Classes\Notifier;
use Aero\Realestate\Classes\Stats;
use Aero\Realestate\Models\Agent;
use Aero\Realestate\Models\Client;
use Aero\Realestate\Models\CommissionRule;
use Aero\Realestate\Models\Contract;
use Aero\Realestate\Models\Lead;
use Aero\Realestate\Models\Offer;
use Aero\Realestate\Models\Owner;
use Aero\Realestate\Models\Payment;
use Aero\Realestate\Models\Property;
use Aero\Realestate\Models\Visit;
use PluginTestCase;

class StatsTest extends PluginTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        Notifier::$muted = true;
    }

    protected function prop(int $tenant, array $over = [], array $offers = [['sale', 100000, 'USD']]): Property
    {
        $place = ['city' => $over['city'] ?? 'La Paz', 'zone' => $over['zone'] ?? 'Calacoto'];   // se denormalizan desde la ubicación; aquí se fijan directo
        unset($over['city'], $over['zone']);
        $p = Property::create($over + ['tenant_id' => $tenant, 'title' => 'P' . uniqid(), 'type' => 'house', 'is_published' => true]);
        Property::whereKey($p->id)->update($place);
        foreach ($offers as $o) {
            Offer::create(['property_id' => $p->id, 'modality' => $o[0], 'amount' => $o[1], 'currency' => $o[2], 'term_months' => $o[3] ?? null]);
        }

        return $p->fresh();
    }

    public function testCatalogCountsByModalityAndStatus(): void
    {
        $this->prop(1, [], [['sale', 1, 'USD'], ['anticretico', 1, 'USD', 24]]);
        $this->prop(1, [], [['rent', 1, 'USD']]);
        $this->prop(1, ['is_published' => false]);
        $this->prop(1, ['status' => 'reserved']);
        $sold = $this->prop(1, ['status' => 'sold']);
        Offer::where('property_id', $sold->id)->update(['status' => 'closed']);   // lo vendido ya no se ofrece
        $this->prop(2, [], [['sale', 1, 'USD']]);

        $s = new Stats(1);
        $this->assertSame(['sale' => 2, 'rent' => 1, 'anticretico' => 1], $s->publishedByModality());   // una propiedad con 2 ofertas cuenta en cada modalidad
        $st = $s->statusBreakdown();
        $this->assertSame([5, 3, 1, 1], [$st['total'], $st['available'], $st['reserved'], $st['closed']]);
        $this->assertSame(1, $st['unpublished']);
    }

    public function testTenantScopeAndFailClosed(): void
    {
        $this->prop(1);
        $this->prop(2);
        $this->assertSame(1, (new Stats(1))->statusBreakdown()['total']);
        $this->assertSame(2, (new Stats(null))->statusBreakdown()['total']);              // superadmin: todo
        $this->assertSame(0, (new Stats(null, 90, true))->statusBreakdown()['total']);     // sin tenant resoluble: nada
    }

    public function testConversionDaysOnMarketAndAgents(): void
    {
        $a1 = Agent::create(['tenant_id' => 1, 'name' => 'Carlos']);
        $a2 = Agent::create(['tenant_id' => 1, 'name' => 'María']);
        CommissionRule::create(['tenant_id' => 1, 'modality' => 'sale', 'basis' => 'percent', 'value' => 3, 'agent_share_percent' => 50]);
        $client = Client::create(['tenant_id' => 1, 'name' => 'Luis']);

        $p = $this->prop(1, ['agent_id' => $a1->id]);
        $lead = Lead::create(['client_id' => $client->id, 'property_id' => $p->id, 'offer_id' => $p->offers->first()->id, 'agent_id' => $a1->id]);
        Property::whereKey($p->id)->update(['published_at' => now()->subDays(40)]);
        Lead::whereKey($lead->id)->update(['created_at' => now()->subDays(20)]);
        app(DealService::class)->close($lead->fresh(), $p->offers->first(), 90000);
        Lead::whereKey($lead->id)->update(['closed_at' => now()->subDays(10)]);

        $lost = Lead::create(['client_id' => $client->id, 'property_id' => $this->prop(1)->id, 'agent_id' => $a2->id]);
        $open = Lead::create(['client_id' => $client->id, 'agent_id' => $a2->id]);

        $s = new Stats(1, 90);
        $c = $s->conversion();
        $this->assertSame([3, 1], [$c['total'], $c['won']]);
        $this->assertSame(33.3, $c['rate']);

        $m = $s->daysOnMarket();
        $this->assertSame(30, $m['closed_avg']);   // publicada hace 40 días, cerrada hace 10

        $rows = collect($s->byAgent())->keyBy('name');
        $this->assertSame([1, 1], [$rows['Carlos']['leads'], $rows['Carlos']['won']]);
        $this->assertSame(100.0, $rows['Carlos']['rate']);
        $this->assertSame(10, $rows['Carlos']['avg_days']);
        $this->assertEquals(2700, $rows['Carlos']['money']['USD']['agency']);     // 3 % de 90.000
        $this->assertEquals(1350, $rows['Carlos']['money']['USD']['agent']);
        $this->assertSame(0, $rows['María']['won']);
        $this->assertSame(2, $rows['María']['leads']);

        $com = $s->commissions();
        $this->assertEquals(2700, $com['USD']['total']);
        $this->assertEquals(2700, $com['USD']['pending']);
        $this->assertSame(['USD'], array_keys($com));                              // sin mezclar monedas
    }

    public function testPeriodExcludesOlderActivity(): void
    {
        $client = Client::create(['tenant_id' => 1, 'name' => 'Luis']);
        $old = Lead::create(['client_id' => $client->id]);
        Lead::whereKey($old->id)->update(['created_at' => now()->subDays(200)]);
        Lead::create(['client_id' => $client->id]);

        $this->assertSame(1, (new Stats(1, 90))->conversion()['total']);
        $this->assertSame(2, (new Stats(1, 365))->conversion()['total']);
        $this->assertSame(90, (new Stats(1, 7))->days);                              // periodo inválido → 90
    }

    public function testVisitsAndTopViewed(): void
    {
        $client = Client::create(['tenant_id' => 1, 'name' => 'Luis']);
        $p = $this->prop(1);
        $q = $this->prop(1);
        Property::whereKey($p->id)->update(['views_count' => 500]);
        Property::whereKey($q->id)->update(['views_count' => 50]);
        foreach ([['done', -5], ['done', -3], ['cancelled', -2], ['confirmed', 2], ['requested', 4]] as [$status, $days]) {
            $v = new Visit(['property_id' => $p->id, 'client_id' => $client->id, 'starts_at' => now()->addDays($days)->setTime(10, 0), 'notes' => 'x']);
            $v->tenant_id = 1;
            $v->status = $status;
            $v->save();
        }

        $v = (new Stats(1))->visits();
        $this->assertSame([2, 1, 2, 1], [$v['done'], $v['cancelled'], $v['upcoming'], $v['requested']]);

        $top = (new Stats(1))->topViewed(5);
        $this->assertSame([$p->id, $q->id], array_column($top, 'id'));
        $this->assertSame([500, 4], [$top[0]['views'], $top[0]['visits']]);           // 4 visitas no canceladas
    }

    public function testAnticreticoCapitalPerCurrencyAndExpiring(): void
    {
        $svc = app(AnticreticoService::class);
        $mk = function (string $cur, float $cap, int $term, $start) use ($svc) {
            $owner = Owner::create(['tenant_id' => 1, 'name' => 'Ana']);
            $client = Client::create(['tenant_id' => 1, 'name' => 'Luis']);
            $p = $this->prop(1, ['owner_id' => $owner->id], [['anticretico', $cap, $cur, $term]]);
            $c = Contract::create(['property_id' => $p->id, 'offer_id' => $p->offers->first()->id, 'client_id' => $client->id, 'capital' => $cap, 'currency' => $cur, 'term_months' => $term]);
            Payment::create(['contract_id' => $c->id, 'type' => 'delivery', 'amount' => $cap, 'paid_on' => now()->toDateString()]);
            $svc->activate($c->fresh(), $start);

            return $c->fresh();
        };
        $mk('USD', 20000, 24, now()->subMonths(6)->toDateString());                         // lejos
        $mk('USD', 10000, 12, now()->subMonths(12)->addDays(20)->toDateString());           // vence en 20 días
        $over = $mk('BOB', 150000, 12, now()->subMonths(12)->subDays(5)->toDateString());    // vencido
        Payment::create(['contract_id' => $over->id, 'type' => 'return', 'amount' => 50000, 'paid_on' => now()->toDateString()]);

        $s = new Stats(1);
        $cap = $s->anticreticoCapital();
        $this->assertEquals(30000, $cap['USD']['capital']);
        $this->assertEquals(2, $cap['USD']['n']);
        $this->assertEquals(150000, $cap['BOB']['capital']);
        $this->assertEquals(100000, $cap['BOB']['pending']);                                // 150.000 − 50.000 devueltos

        $k = $s->contractStatuses();
        $this->assertSame([3, 1, 1], [$k['active'], $k['expiring'], $k['overdue']]);

        $exp = $s->expiring();
        $this->assertCount(2, $exp);
        $this->assertLessThan($exp[1]['left'], $exp[0]['left']);                              // el vencido primero
    }

    public function testMonthlySeriesAndAttention(): void
    {
        $client = Client::create(['tenant_id' => 1, 'name' => 'Luis']);
        Lead::create(['client_id' => $client->id]);
        $l = Lead::create(['client_id' => $client->id]);
        Lead::whereKey($l->id)->update(['created_at' => now()->subMonths(2)]);

        $m = (new Stats(1))->monthly(6);
        $this->assertCount(6, $m['labels']);
        $this->assertSame(2, array_sum($m['leads']));
        $this->assertSame(1, end($m['leads']));

        $a = (new Stats(1))->attention();
        $this->assertSame(2, $a['unassigned_leads']);
    }

    public function testDemandGroupsByPropertyLocation(): void
    {
        $client = Client::create(['tenant_id' => 1, 'name' => 'Luis']);
        $lp = $this->prop(1, ['city' => 'La Paz', 'zone' => 'Calacoto', 'type' => 'apartment']);
        $sc = $this->prop(1, ['city' => 'Santa Cruz', 'zone' => 'Equipetrol']);
        foreach ([$lp, $lp, $sc] as $p) {
            Lead::create(['client_id' => $client->id, 'property_id' => $p->id]);
        }

        $d = (new Stats(1))->demand();
        $this->assertSame(['La Paz' => 2, 'Santa Cruz' => 1], array_column($d['city'], 'n', 'k'));
        $this->assertSame('Departamento', $d['type'][0]['k']);
    }

    public function testChartsEscapeAndHandleEmpty(): void
    {
        $this->assertStringContainsString('Sin datos', Charts::hbars([]));
        $html = Charts::hbars([['k' => '<script>x</script>', 'n' => 3]]);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('<svg', Charts::columns(['Oct', 'Nov'], ['A' => [1, 2], 'B' => [0, 0]], ['#111', '#222']));
        $this->assertStringContainsString('Sin datos', Charts::stacked(['A' => [0, '#111']]));
    }
}
