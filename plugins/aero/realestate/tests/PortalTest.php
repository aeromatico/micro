<?php namespace Aero\Realestate\Tests;

use Aero\Realestate\Classes\Contact;
use Aero\Realestate\Classes\PortalAvailability;
use Aero\Realestate\Classes\PortalSearch;
use Aero\Realestate\Classes\PortalService;
use Aero\Realestate\Classes\RealestateException;
use Aero\Realestate\Classes\VisitService;
use Aero\Realestate\Models\Agent;
use Aero\Realestate\Models\Change;
use Aero\Realestate\Models\Client;
use Aero\Realestate\Models\Feature;
use Aero\Realestate\Models\Lead;
use Aero\Realestate\Models\Offer;
use Aero\Realestate\Models\Property;
use Aero\Realestate\Models\RealestateSettings;
use Aero\Realestate\Models\SavedSearch;
use Aero\Realestate\Models\Visit;
use PluginTestCase;

class PortalTest extends PluginTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        \Aero\Realestate\Classes\Notifier::$muted = true;
    }

    protected function prop(array $over = [], array $offers = [['sale', 90000, 'USD']], int $tenant = 1): Property
    {
        $published = $over['published_at'] ?? null;
        unset($over['published_at']);
        $p = Property::create($over + ['tenant_id' => $tenant, 'title' => 'Casa ' . uniqid(), 'type' => 'house', 'is_published' => true]);
        $published && Property::whereKey($p->id)->update(['published_at' => $published]);
        foreach ($offers as $o) {
            Offer::create(['property_id' => $p->id, 'modality' => $o[0], 'amount' => $o[1], 'currency' => $o[2], 'term_months' => $o[3] ?? null]);
        }

        return $p;
    }

    protected function ids(array $f, int $tenant = 1): array
    {
        return PortalSearch::query($tenant, PortalSearch::sanitize($f))->pluck('id')->all();
    }

    public function testOnlyPublishedWithAvailableOffersAndOwnTenant(): void
    {
        $ok = $this->prop();
        $this->prop(['is_published' => false]);
        $sold = $this->prop();
        Offer::where('property_id', $sold->id)->update(['status' => 'closed']);
        $this->prop([], [['sale', 1000, 'USD']], 2);

        $this->assertSame([$ok->id], $this->ids([]));
    }

    public function testPriceFilterLooksAtOffersPerModality(): void
    {
        $a = $this->prop([], [['sale', 90000, 'USD'], ['anticretico', 20000, 'USD', 24]]);
        $b = $this->prop([], [['rent', 400, 'USD']]);

        $this->assertSame([$a->id], $this->ids(['modality' => 'sale', 'price_max' => 100000]));
        $this->assertSame([], $this->ids(['modality' => 'sale', 'price_max' => 50000]));
        $this->assertSame([$b->id], $this->ids(['modality' => 'rent']));
        // el precio de venta de $a no cuenta para su capital de anticrético
        $this->assertSame([$a->id], $this->ids(['modality' => 'anticretico', 'capital_min' => 15000, 'capital_max' => 25000]));
        $this->assertSame([], $this->ids(['modality' => 'anticretico', 'capital_max' => 10000]));
    }

    public function testAnticreticoTermAndCurrencyFilters(): void
    {
        $a = $this->prop([], [['anticretico', 20000, 'USD', 24]]);
        $this->prop([], [['anticretico', 150000, 'BOB', 12]]);

        $this->assertSame([$a->id], $this->ids(['modality' => 'anticretico', 'term_min' => 18]));
        $this->assertSame([$a->id], $this->ids(['modality' => 'anticretico', 'currency' => 'USD']));
        $this->assertCount(2, $this->ids(['modality' => 'anticretico']));
    }

    public function testFeaturesAreAllRequiredAndSortByPrice(): void
    {
        $pool = Feature::create(['tenant_id' => 1, 'name' => 'Piscina']);
        $garden = Feature::create(['tenant_id' => 1, 'name' => 'Jardín']);
        $cheap = $this->prop([], [['sale', 50000, 'USD']]);
        $dear = $this->prop([], [['sale', 150000, 'USD']]);
        $dear->features()->attach([$pool->id, $garden->id]);
        $cheap->features()->attach([$pool->id]);

        $this->assertSame([$dear->id], $this->ids(['features' => [$pool->id, $garden->id]]));
        $this->assertSame([$cheap->id, $dear->id], $this->ids(['sort' => 'price_asc']));
        $this->assertSame([$dear->id, $cheap->id], $this->ids(['sort' => 'price_desc']));
    }

    public function testSanitizeDropsJunkAndCastsTypes(): void
    {
        $f = PortalSearch::sanitize(['modality' => 'drop table', 'type' => 'house', 'price_min' => '100', 'price_max' => '-5', 'bedrooms' => 'abc', 'features' => ['3', 'x', '3'], 'sort' => 'evil', 'q' => str_repeat('a', 500), 'foo' => 'bar']);
        $this->assertEquals(['type' => 'house', 'price_min' => 100.0, 'features' => [3], 'q' => str_repeat('a', 80)], $f);
    }

    public function testVisitSlotsRespectHoursAgentAndNotice(): void
    {
        RealestateSettings::create(['tenant_id' => 1, 'visit_start_time' => '09:00', 'visit_end_time' => '12:00', 'visit_minutes' => 60, 'visit_notice_hours' => 0, 'visit_days' => [1, 2, 3, 4, 5, 6, 7]]);
        $agent = Agent::create(['tenant_id' => 1, 'name' => 'Carlos']);
        $p = $this->prop(['agent_id' => $agent->id]);
        $client = Client::create(['tenant_id' => 1, 'name' => 'Luis']);
        $day = now()->addDays(3)->startOfDay();

        $this->assertSame(['09:00', '10:00', '11:00'], PortalAvailability::slots($p, $day));

        app(VisitService::class)->schedule(['property_id' => $p->id, 'client_id' => $client->id, 'starts_at' => $day->copy()->setTime(10, 0)]);
        $this->assertSame(['09:00', '11:00'], PortalAvailability::slots($p, $day));

        // otra propiedad del MISMO agente también queda ocupada a esa hora
        $other = $this->prop(['agent_id' => $agent->id]);
        $this->assertSame(['09:00', '11:00'], PortalAvailability::slots($other, $day));

        $this->assertSame([], PortalAvailability::slots($p, now()->subDay()));            // pasado
        $this->assertSame([], PortalAvailability::slots($p, now()->addDays(90)));         // fuera de la ventana
    }

    public function testSlotsSkipClosedWeekdays(): void
    {
        RealestateSettings::create(['tenant_id' => 1, 'visit_days' => [1, 2, 3, 4, 5], 'visit_notice_hours' => 0]);
        $p = $this->prop();
        $sunday = now()->next('Sunday');
        $this->assertSame([], PortalAvailability::slots($p, $sunday));
    }

    public function testInquiryCreatesClientLeadAndReusesThem(): void
    {
        $agent = Agent::create(['tenant_id' => 1, 'name' => 'Carlos']);
        $p = $this->prop(['agent_id' => $agent->id]);
        $svc = app(PortalService::class);

        [$client, $lead, $visit] = $svc->inquire($p, ['name' => 'Luis', 'email' => 'luis@example.com', 'phone' => '7000 0000', 'message' => 'Hola', 'offer_id' => $p->offers->first()->id]);
        $this->assertSame('portal', $client->source);
        $this->assertSame('new', $lead->stage);
        $this->assertSame($agent->id, $lead->agent_id);
        $this->assertNull($visit);
        $this->assertSame('70000000', $client->phone);

        [$client2, $lead2] = $svc->inquire($p, ['name' => 'Luis P.', 'phone' => '70000000']);
        $this->assertSame($client->id, $client2->id);   // mismo teléfono → mismo cliente
        $this->assertSame($lead->id, $lead2->id);       // misma propiedad abierta → misma oportunidad
        $this->assertSame(1, Lead::count());
        $this->assertSame(1, Client::count());
    }

    public function testInquiryWithVisitIsRequestedAndAgentClashRejected(): void
    {
        $agent = Agent::create(['tenant_id' => 1, 'name' => 'Carlos']);
        $p = $this->prop(['agent_id' => $agent->id]);
        $when = now()->addDays(2)->setTime(10, 0)->toDateTimeString();
        $svc = app(PortalService::class);

        [, $lead, $visit] = $svc->inquire($p, ['name' => 'Luis', 'email' => 'luis@example.com', 'visit_at' => $when]);
        $this->assertSame('requested', $visit->status);
        $this->assertSame('portal', $visit->source);
        $this->assertSame('visit_scheduled', $lead->fresh()->stage);

        try {
            $svc->inquire($p, ['name' => 'Ana', 'email' => 'ana@example.com', 'visit_at' => $when]);
            $this->fail('Debió chocar');
        } catch (RealestateException $e) {
            $this->assertStringContainsString('visita', mb_strtolower($e->getMessage()));
        }
        $this->assertSame(1, Visit::count());
        $this->assertSame(1, Client::where('email', 'ana@example.com')->count() ?: 1);
    }

    public function testInquiryValidatesContactData(): void
    {
        $p = $this->prop();
        foreach ([['name' => ''], ['name' => 'Luis'], ['name' => 'Luis', 'email' => 'no-es-correo']] as $bad) {
            try {
                app(PortalService::class)->inquire($p, $bad);
                $this->fail('Debió rechazar ' . json_encode($bad));
            } catch (RealestateException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
    }

    public function testInquiryNeverCrossesTenants(): void
    {
        $a = $this->prop([], [['sale', 1, 'USD']], 1);
        $b = $this->prop([], [['sale', 1, 'USD']], 2);
        [$ca] = app(PortalService::class)->inquire($a, ['name' => 'Luis', 'email' => 'luis@example.com']);
        [$cb] = app(PortalService::class)->inquire($b, ['name' => 'Luis', 'email' => 'luis@example.com']);

        $this->assertNotSame($ca->id, $cb->id);
        $this->assertSame([1, 2], [$ca->tenant_id, $cb->tenant_id]);
    }

    public function testPriceAndStatusChangesAreLoggedOnlyWhenPublished(): void
    {
        $p = $this->prop();
        $offer = $p->offers->first();
        $offer->update(['amount' => 85000]);
        $p->update(['status' => 'reserved']);

        $this->assertSame(['price', 'status'], Change::orderBy('id')->pluck('kind')->all());
        $price = Change::where('kind', 'price')->first();
        $this->assertEquals([90000, 85000], [$price->old_value, $price->new_value]);

        $draft = $this->prop(['is_published' => false]);
        $draft->offers->first()->update(['amount' => 1]);
        $this->assertSame(2, Change::count());   // lo no publicado no avisa a nadie
    }

    public function testSavedSearchMatchesOnlyNewerPublications(): void
    {
        $this->prop(['published_at' => now()->subDay()]);                    // anterior a la búsqueda
        $s = SavedSearch::create(['tenant_id' => 1, 'name' => 'Casas', 'criteria' => ['type' => 'house'], 'email' => 'luis@example.com']);
        $this->assertNotEmpty($s->token);

        $new = $this->prop(['published_at' => now()->addMinute()]);
        $found = PortalSearch::query(1, (array) $s->criteria)->where('published_at', '>', $s->last_alert_at)->pluck('id')->all();

        $this->assertSame([$new->id], $found);
    }

    public function testWhatsappNumberNormalisesBolivianPhones(): void
    {
        $this->assertSame('59170000000', Contact::whatsappNumber('7000 0000'));
        $this->assertSame('59170000000', Contact::whatsappNumber('+591 70000000'));
        $this->assertNull(Contact::whatsappNumber('123'));
        $this->assertNull(Contact::whatsappNumber(null));
        $this->assertStringStartsWith('https://wa.me/59170000000?text=Hola%20', Contact::whatsappUrl('70000000', 'Hola mundo'));
    }

    public function testOfferLabelsPerModality(): void
    {
        $p = $this->prop([], [['sale', 90000, 'USD'], ['rent', 500, 'BOB'], ['anticretico', 25000, 'USD', 24]]);
        $labels = $p->offers->pluck('label', 'modality')->all();
        $this->assertSame(['sale' => 'USD 90.000', 'rent' => 'BOB 500 / mes', 'anticretico' => 'Capital USD 25.000 · 24 meses'], $labels);
    }
}
