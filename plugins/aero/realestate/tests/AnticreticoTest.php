<?php namespace Aero\Realestate\Tests;

use Aero\Realestate\Classes\AnticreticoService;
use Aero\Realestate\Classes\Notifier;
use Aero\Realestate\Classes\RealestateException;
use Aero\Realestate\Models\Client;
use Aero\Realestate\Models\Contract;
use Aero\Realestate\Models\ContractDocument;
use Aero\Realestate\Models\Offer;
use Aero\Realestate\Models\Owner;
use Aero\Realestate\Models\Payment;
use Aero\Realestate\Models\Property;
use Carbon\Carbon;
use PluginTestCase;

class AnticreticoTest extends PluginTestCase
{
    protected AnticreticoService $svc;

    public function setUp(): void
    {
        parent::setUp();
        $this->svc = app(AnticreticoService::class);
    }

    protected function contract(int $tenant = 1, float $capital = 20000): Contract
    {
        $owner = Owner::create(['tenant_id' => $tenant, 'name' => 'Ana']);
        $client = Client::create(['tenant_id' => $tenant, 'name' => 'Luis']);
        $p = Property::create(['tenant_id' => $tenant, 'title' => 'Depto', 'type' => 'apartment', 'owner_id' => $owner->id]);
        $offer = Offer::create(['property_id' => $p->id, 'modality' => 'anticretico', 'amount' => $capital, 'currency' => 'USD', 'term_months' => 24]);

        return Contract::create([
            'property_id' => $p->id, 'offer_id' => $offer->id, 'client_id' => $client->id,
            'capital' => $capital, 'currency' => 'USD', 'term_months' => 24,
        ]);
    }

    protected function deliver(Contract $c, float $amount): Payment
    {
        return Payment::create(['contract_id' => $c->id, 'type' => 'delivery', 'amount' => $amount, 'paid_on' => now()->toDateString()]);
    }

    public function testNewContractIsDraftWithCodeAndOwnerFromProperty(): void
    {
        $c = $this->contract();
        $this->assertSame('draft', $c->status);
        $this->assertSame('ANT-' . str_pad((string) $c->id, 5, '0', STR_PAD_LEFT), $c->fresh()->code);
        $this->assertNotNull($c->owner_id);
        $this->assertSame(1, $c->tenant_id);
    }

    public function testCannotActivateWithoutFullCapitalDelivered(): void
    {
        $c = $this->contract();
        $this->deliver($c, 5000);
        $this->expectException(RealestateException::class);
        $this->svc->activate($c->fresh());
    }

    public function testActivationSetsDatesAndMovesPropertyAndOffer(): void
    {
        $c = $this->contract();
        $this->deliver($c, 20000);
        $this->svc->activate($c->fresh(), '2026-01-15');
        $c = $c->fresh();

        $this->assertSame('active', $c->status);
        $this->assertSame('2028-01-15', $c->end_date->toDateString());
        $this->assertSame('anticretico', $c->property->status);
        $this->assertSame('closed', $c->offer->status);
    }

    public function testRenewalExtendsTermAndLogs(): void
    {
        $c = $this->contract();
        $this->deliver($c, 20000);
        $this->svc->activate($c->fresh(), '2026-01-15');
        $this->svc->renew($c->fresh(), 12, 22000, 'Sube el capital');
        $c = $c->fresh();

        $this->assertSame('2029-01-15', $c->end_date->toDateString());
        $this->assertSame(36, (int) $c->term_months);
        $this->assertEquals(22000, (float) $c->capital);
        $this->assertSame(1, $c->events()->where('type', 'renewed')->count());
    }

    public function testReturnsTrackStatusAndFinishNeedsFullReturn(): void
    {
        $c = $this->contract();
        $this->deliver($c, 20000);
        $this->svc->activate($c->fresh(), '2026-01-15');

        $this->assertSame('pending', $c->fresh()->return_status);
        Payment::create(['contract_id' => $c->id, 'type' => 'return', 'amount' => 8000, 'paid_on' => now()->toDateString()]);
        $this->assertSame('partial', $c->fresh()->return_status);

        try {
            $this->svc->finish($c->fresh());
            $this->fail('No debió finalizar con devolución parcial');
        } catch (RealestateException $e) {
            $this->assertStringContainsString('12.000', $e->getMessage());
        }

        $this->svc->markDispute($c->fresh(), 'No coincide el monto');
        $this->assertSame('dispute', $c->fresh()->return_status);
        $this->svc->resolveDispute($c->fresh());
        $this->assertSame('partial', $c->fresh()->return_status);

        Payment::create(['contract_id' => $c->id, 'type' => 'return', 'amount' => 12000, 'paid_on' => now()->toDateString()]);
        $this->assertSame('completed', $c->fresh()->return_status);
        $this->svc->finish($c->fresh());
        $this->assertSame('finished', $c->fresh()->status);
        $this->assertSame('available', $c->fresh()->property->status);
    }

    public function testReturnCannotExceedDelivered(): void
    {
        $c = $this->contract();
        $this->deliver($c, 20000);
        $this->svc->activate($c->fresh(), '2026-01-15');
        $this->expectException(\ApplicationException::class);
        Payment::create(['contract_id' => $c->id, 'type' => 'return', 'amount' => 25000, 'paid_on' => now()->toDateString()]);
    }

    public function testCancelReleasesPropertyAndOffer(): void
    {
        $c = $this->contract();
        $this->svc->reserve($c);
        $this->assertSame('reserved', $c->fresh()->property->status);
        $this->svc->cancel($c->fresh(), 'El cliente desistió');
        $this->assertSame('available', $c->fresh()->property->status);
        $this->assertSame('available', $c->fresh()->offer->status);
    }

    public function testSignedContractIdentityIsLocked(): void
    {
        $c = $this->contract();
        $this->deliver($c, 20000);
        $this->svc->activate($c->fresh(), '2026-01-15');
        $other = Client::create(['tenant_id' => 1, 'name' => 'Otro']);

        $this->expectException(\ApplicationException::class);
        $c = $c->fresh();
        $c->client_id = $other->id;
        $c->save();
    }

    public function testMissingDocumentsUntilVerified(): void
    {
        $c = $this->contract();
        $this->assertSame(['public_deed', 'property_registry'], $this->svc->missingDocuments($c));
        ContractDocument::create(['contract_id' => $c->id, 'type' => 'public_deed', 'status' => 'verified']);
        $this->assertSame(['property_registry'], $this->svc->missingDocuments($c));
    }

    public function testExpiryThresholdsFireOncePerStep(): void
    {
        $c = new Contract();
        $c->end_date = now()->addDays(45);
        $this->assertSame(60, Notifier::thresholdFor($c));

        $c->last_alert_days = 60;
        $this->assertNull(Notifier::thresholdFor($c));      // ya avisado a 60

        $c->end_date = now()->addDays(6);
        $this->assertSame(7, Notifier::thresholdFor($c));   // cruzó el siguiente

        $c->last_alert_days = 7;
        $c->end_date = now()->subDays(3);
        $this->assertSame(0, Notifier::thresholdFor($c));   // vencido

        $c->end_date = now()->addDays(90);
        $c->last_alert_days = null;
        $this->assertNull(Notifier::thresholdFor($c));      // aún lejos
    }

    public function testTenantIsolation(): void
    {
        $this->contract(1);
        $this->contract(2);
        $this->assertSame(1, Contract::forTenant(1)->count());
        $this->assertSame(0, Contract::forTenant(null)->count());
    }
}
