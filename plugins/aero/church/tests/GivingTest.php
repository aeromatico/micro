<?php namespace Aero\Church\Tests;

use Aero\Church\Classes\ChurchException;
use Aero\Church\Classes\ContributionService;
use Aero\Church\Classes\GivingStats;
use Aero\Church\Models\Contribution;
use Aero\Church\Models\Fund;
use Aero\Church\Models\Member;
use PluginTestCase;

class GivingTest extends PluginTestCase
{
    protected function fund(int $tenant = 1, string $kind = 'tithe'): Fund
    {
        return Fund::create(['tenant_id' => $tenant, 'name' => 'Diezmo ' . $tenant . $kind, 'kind' => $kind]);
    }

    protected function give(array $over = []): Contribution
    {
        return Contribution::create($over + [
            'tenant_id' => 1, 'fund_id' => $this->fund()->id, 'amount' => 100, 'currency' => 'BOB', 'date' => now()->toDateString(), 'method' => 'cash',
        ]);
    }

    public function testCashIsConfirmedAndQrIsPending(): void
    {
        $this->assertSame('confirmed', $this->give()->status);
        $qr = $this->give(['method' => 'qr']);
        $this->assertSame('pending', $qr->status);
        $this->assertNull($qr->confirmed_at);
    }

    public function testAnonymousStoresNoIdentity(): void
    {
        $m = Member::create(['tenant_id' => 1, 'first_name' => 'Ana', 'status' => 'active']);
        $c = $this->give(['is_anonymous' => true, 'member_id' => $m->id, 'donor_name' => 'Pedro']);

        $this->assertNull($c->member_id);
        $this->assertNull($c->donor_name);
        $this->assertSame('Anónimo', $c->donor_label);

        $this->expectException(ChurchException::class);
        app(ContributionService::class)->receiptNumber($c);
    }

    public function testConfirmedContributionIsLockedAndNotDeletable(): void
    {
        $c = $this->give();

        $c->amount = 999;
        try {
            $c->save();
            $this->fail('Debió rechazar editar el monto de un aporte confirmado');
        } catch (\ApplicationException $e) {
            $this->assertStringContainsString('no se edita', $e->getMessage());
        }

        try {
            $c->fresh()->delete();
            $this->fail('Debió rechazar eliminar un aporte confirmado');
        } catch (\ApplicationException $e) {
            $this->assertStringContainsString('anúlalo', $e->getMessage());
        }

        $this->assertTrue($c->fresh()->markCancelled());
        $this->assertSame('cancelled', $c->fresh()->status);
    }

    public function testFiresEventsOnConfirmAndCancel(): void
    {
        $fired = [];
        \Event::listen('aero.church.contributionConfirmed', function ($c) use (&$fired) { $fired[] = 'confirmed'; });
        \Event::listen('aero.church.contributionCancelled', function ($c) use (&$fired) { $fired[] = 'cancelled'; });

        $c = $this->give();
        $c->markCancelled();
        $this->assertSame(['confirmed', 'cancelled'], $fired);
    }

    public function testQrPaidConfirmsPendingAndRevivesCancelled(): void
    {
        $svc = app(ContributionService::class);
        $c = $this->give(['method' => 'qr']);
        $c->payment_reference = 'REF-1';
        $c->save();

        $svc->onQrStatusChanged((object) ['internal_reference' => 'REF-1', 'tenant_id' => 2, 'status' => 'paid']);
        $this->assertSame('pending', $c->fresh()->status, 'Un QR de otro tenant no concilia');

        $svc->onQrStatusChanged((object) ['internal_reference' => 'REF-1', 'tenant_id' => 1, 'status' => 'paid']);
        $this->assertSame('confirmed', $c->fresh()->status);

        $d = $this->give(['method' => 'qr']);
        $d->payment_reference = 'REF-2';
        $d->save();
        $d->markCancelled();
        $svc->onQrStatusChanged((object) ['internal_reference' => 'REF-2', 'tenant_id' => 1, 'status' => 'paid']);
        $this->assertSame('confirmed', $d->fresh()->status, 'Dinero recibido tras anular: no se pierde');
    }

    public function testReceiptNumberingIsSequentialPerTenantAndStable(): void
    {
        $svc = app(ContributionService::class);
        $a = $this->give();
        $b = $this->give();
        $other = Contribution::create(['tenant_id' => 2, 'fund_id' => $this->fund(2)->id, 'amount' => 5, 'currency' => 'BOB', 'date' => now()->toDateString(), 'method' => 'cash']);

        $this->assertSame('R-000001', $svc->receiptNumber($a));
        $this->assertSame('R-000002', $svc->receiptNumber($b));
        $this->assertSame('R-000001', $svc->receiptNumber($a->fresh()), 'Reemitir devuelve el mismo número');
        $this->assertSame('R-000001', $svc->receiptNumber($other), 'Cada iglesia numera aparte');
    }

    public function testStatsOnlyCountConfirmedAndNeverExposeNames(): void
    {
        $this->give(['amount' => 100]);
        $this->give(['amount' => 50, 'method' => 'qr']);          // pendiente: no cuenta
        $c = $this->give(['amount' => 30]);
        $c->markCancelled();                                         // anulado: no cuenta
        Contribution::create(['tenant_id' => 2, 'fund_id' => $this->fund(2)->id, 'amount' => 999, 'currency' => 'BOB', 'date' => now()->toDateString(), 'method' => 'cash']);

        $stats = new GivingStats(now()->startOfYear()->toDateString(), now()->toDateString(), null, 1);

        $this->assertSame(['BOB' => ['total' => 100.0, 'count' => 1]], $stats->totals());
        $this->assertSame(100.0, (float) $stats->byFund()[0]->total);
        $this->assertSame(['cash'], array_map(fn ($r) => $r->label, $stats->byMethod()));
        $this->assertSame(100.0, array_sum(array_map(fn ($m) => array_sum($m), $stats->monthly())));
        $this->assertStringNotContainsString('Anónimo', json_encode($stats->byFund()));
    }
}
