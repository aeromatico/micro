<?php namespace Aero\Realestate\Tests;

use Aero\Realestate\Classes\AgreementPdf;
use Aero\Realestate\Classes\AgreementService;
use Aero\Realestate\Classes\AmountInWords;
use Aero\Realestate\Classes\DefaultTemplates;
use Aero\Realestate\Classes\Notifier;
use Aero\Realestate\Classes\RealestateException;
use Aero\Realestate\Classes\TemplateRenderer;
use Aero\Realestate\Models\Agreement;
use Aero\Realestate\Models\AgreementTemplate;
use Aero\Realestate\Models\Client;
use Aero\Realestate\Models\Document;
use Aero\Realestate\Models\Offer;
use Aero\Realestate\Models\Owner;
use Aero\Realestate\Models\Property;
use PluginTestCase;

class AgreementTest extends PluginTestCase
{
    protected AgreementService $svc;

    public function setUp(): void
    {
        parent::setUp();
        Notifier::$muted = true;
        $this->svc = app(AgreementService::class);
        foreach (DefaultTemplates::all() as $type => $t) {
            AgreementTemplate::create(['tenant_id' => null, 'type' => $type, 'name' => $t['name'], 'body' => $t['body']]);
        }
    }

    protected function setUpDeal(string $type = 'rent', bool $reviewed = true, int $tenant = 1): array
    {
        $owner = Owner::create(['tenant_id' => $tenant, 'name' => 'Ana Rojas', 'document' => '1234567 LP', 'address' => 'Av. Arce 1', 'civil_status' => 'casada']);
        $client = Client::create(['tenant_id' => $tenant, 'name' => 'Luis Pérez', 'document' => '7654321 SC']);
        $p = Property::create(['tenant_id' => $tenant, 'title' => 'Depto Sopocachi', 'type' => 'apartment', 'owner_id' => $owner->id, 'city' => 'La Paz', 'address' => 'Calle 20 de Octubre 100', 'built_area' => 85]);
        $offer = Offer::create(['property_id' => $p->id, 'modality' => $type === 'rent' ? 'rent' : 'sale', 'amount' => 450, 'currency' => 'USD', 'term_months' => 12, 'deposit' => 450]);
        $tpl = AgreementTemplate::where('type', $type)->whereNull('tenant_id')->first()->duplicate();
        $tpl->tenant_id = $tenant;
        $tpl->saveQuietly();
        $reviewed && $tpl->markReviewed('Estudio Jurídico Vargas', 'OK');

        return [$p, $owner, $client, $offer, $tpl];
    }

    protected function generate(string $type = 'rent', bool $reviewed = true, array $over = []): Agreement
    {
        [$p, $owner, $client, $offer, $tpl] = $this->setUpDeal($type, $reviewed);

        return $this->svc->generate($over + [
            'type' => $type, 'template_id' => $tpl->id, 'property_id' => $p->id, 'client_id' => $client->id, 'offer_id' => $offer->id,
            'start_date' => '2026-11-01', 'variables' => ['folio_real' => '2.01.0.99.0012345'],
        ]);
    }

    public function testRendererSubstitutesBlanksAndFlagsUnknownVariables(): void
    {
        $out = TemplateRenderer::render('A {{ owner.name }} B {{ owner.civil_status }} C {{ nope }} D {{ extra_clauses }}', ['owner.name' => '<b>Ana</b>', 'owner.civil_status' => '', 'extra_clauses' => null]);
        $this->assertStringContainsString('&lt;b&gt;Ana&lt;/b&gt;', $out);          // escapa
        $this->assertStringContainsString(TemplateRenderer::BLANK, $out);           // vacío → línea
        $this->assertStringContainsString('variable desconocida: nope', $out);      // desconocida → visible
        $this->assertSame([], TemplateRenderer::unknown('{{ owner.name }}', TemplateRenderer::known()));
        $this->assertSame(['x.y'], TemplateRenderer::unknown('{{ x.y }}', TemplateRenderer::known()));
    }

    public function testBaseTemplatesOnlyUseKnownVariables(): void
    {
        foreach (DefaultTemplates::all() as $type => $t) {
            $this->assertSame([], TemplateRenderer::unknown($t['body'], TemplateRenderer::known()), "El modelo {$type} usa variables que no existen");
        }
    }

    public function testGenerateFillsTextFromRealData(): void
    {
        $a = $this->generate('rent');
        $this->assertSame('draft', $a->status);
        $this->assertSame('CTR-' . str_pad((string) $a->id, 5, '0', STR_PAD_LEFT), $a->fresh()->code);
        $body = $a->fresh()->body;
        $this->assertStringContainsString('Ana Rojas', $body);
        $this->assertStringContainsString('Luis Pérez', $body);
        $this->assertStringContainsString('450,00 USD', $body);
        $this->assertStringContainsString('Cuatrocientos cincuenta 00/100 dólares americanos', $body);
        $this->assertStringContainsString('2.01.0.99.0012345', $body);
        $this->assertSame('2027-11-01', $a->fresh()->end_date->toDateString());     // plazo de la oferta (12 meses)
        $this->assertStringNotContainsString('{{', $body);
    }

    public function testApprovalNeedsReviewedTemplateAndCompleteData(): void
    {
        $unreviewed = $this->generate('rent', false);
        try {
            $this->svc->approve($unreviewed);
            $this->fail('No debió aprobar sin revisión legal');
        } catch (RealestateException $e) {
            $this->assertStringContainsString('revisión legal', $e->getMessage());
        }
        $this->assertSame('BORRADOR — SIN REVISIÓN LEGAL', AgreementPdf::watermark($unreviewed->fresh()));

        $ok = $this->generate('rent', true);
        $this->svc->approve($ok);
        $this->assertSame('approved', $ok->fresh()->status);
        $this->assertNull(AgreementPdf::watermark($ok->fresh()));
    }

    public function testEditingTemplateInvalidatesItsLegalReview(): void
    {
        $a = $this->generate('rent', true);
        $tpl = $a->template;
        $this->assertTrue($tpl->fresh()->is_reviewed);
        $tpl->body .= '<p>Cláusula nueva</p>';
        $tpl->save();

        $this->assertSame(2, (int) $tpl->fresh()->version);
        $this->assertFalse($tpl->fresh()->is_reviewed);
        $this->expectException(RealestateException::class);       // el contrato generó con v1; la plantilla ya cambió
        $this->svc->approve($a);
    }

    public function testApprovedTextIsLockedUntilReopened(): void
    {
        $a = $this->generate('rent', true);
        $this->svc->approve($a);

        try {
            $x = $a->fresh();
            $x->body = '<p>cambio</p>';
            $x->save();
            $this->fail('Debió bloquear la edición');
        } catch (\ApplicationException $e) {
            $this->assertStringContainsString('reábrelo', $e->getMessage());
        }

        $this->svc->reopen($a->fresh(), 'Error en el canon');
        $this->assertSame('draft', $a->fresh()->status);
    }

    public function testSignRenewAndFinishRent(): void
    {
        $a = $this->generate('rent', true);
        $this->svc->approve($a);
        $this->svc->sign($a->fresh(), '2026-11-01');
        $this->assertSame('signed', $a->fresh()->status);

        $this->svc->renew($a->fresh(), 6, 500, 'Sube el canon');
        $x = $a->fresh();
        $this->assertSame('2028-05-01', $x->end_date->toDateString());
        $this->assertEquals(500, (float) $x->amount);
        $this->assertSame(1, $x->events()->where('type', 'renewed')->count());

        $this->svc->finish($x);
        $this->assertSame('finished', $a->fresh()->status);
    }

    public function testRentNeedsDatesToSignAndSignedCannotBeCancelled(): void
    {
        [$p, , $client, , $tpl] = $this->setUpDeal('rent', true);
        $a = $this->svc->generate(['type' => 'rent', 'template_id' => $tpl->id, 'property_id' => $p->id, 'client_id' => $client->id, 'amount' => 300]);
        $this->svc->approve($a);
        try {
            $this->svc->sign($a->fresh());
            $this->fail('Debió pedir fechas');
        } catch (RealestateException $e) {
            $this->assertStringContainsString('fecha de inicio', $e->getMessage());
        }

        $b = $this->generate('rent', true);
        $this->svc->approve($b);
        $this->svc->sign($b->fresh());
        $this->expectException(RealestateException::class);
        $this->svc->cancel($b->fresh(), 'x');
    }

    public function testArrasAreNotRenewable(): void
    {
        $a = $this->generate('arras', true, ['amount' => 5000, 'total_price' => 90000, 'end_date' => '2026-12-15']);
        $this->assertStringContainsString('Noventa mil', $a->fresh()->body);
        $this->svc->approve($a);
        $this->svc->sign($a->fresh());
        $this->expectException(RealestateException::class);
        $this->svc->renew($a->fresh(), 3);
    }

    public function testRentalExpiryScopeOnlySignedRents(): void
    {
        $rent = $this->generate('rent', true);
        $this->svc->approve($rent);
        $this->svc->sign($rent->fresh(), '2026-01-01');
        $rent->forceFill(['end_date' => now()->addDays(20)])->saveQuietly();
        $draft = $this->generate('rent', true);
        $draft->forceFill(['end_date' => now()->addDays(20)])->saveQuietly();

        $this->assertSame([$rent->id], Agreement::expiring(60)->pluck('id')->all());
    }

    public function testDocumentsNeedASubjectAndOnlyAdminsVerify(): void
    {
        $owner = Owner::create(['tenant_id' => 1, 'name' => 'Ana']);
        try {
            Document::create(['type' => 'id_card', 'name' => 'CI']);
            $this->fail('Debió pedir a qué pertenece');
        } catch (\ApplicationException $e) {
            $this->assertStringContainsString('a qué pertenece', $e->getMessage());
        }
        $d = Document::create(['owner_id' => $owner->id, 'type' => 'id_card', 'name' => 'CI de Ana']);
        $this->assertSame('pending', $d->status);
        $this->assertSame(1, $d->tenant_id);
        $this->assertStringContainsString('Propietario: Ana', $d->subject);

        $d->status = 'verified';
        $d->save();   // sin usuario de panel (servicio): permitido y registra la fecha
        $this->assertNotNull($d->fresh()->verified_at);
    }

    public function testTenantIsolation(): void
    {
        $this->generate('rent', true);
        $this->assertSame(1, Agreement::forTenant(1)->count());
        $this->assertSame(0, Agreement::forTenant(2)->count());
        $this->assertSame(0, Agreement::forTenant(null)->count());
    }

    public function testAmountInWords(): void
    {
        $this->assertSame('Veinte mil 00/100 dólares americanos', AmountInWords::money(20000, 'USD'));
        $this->assertSame('Mil doscientos cincuenta 50/100 bolivianos', AmountInWords::money(1250.5, 'BOB'));
    }

    public function testPdfOrHtmlIsProduced(): void
    {
        $a = $this->generate('rent', false);
        $maker = new AgreementPdf();
        $this->assertStringContainsString('BORRADOR', $maker->html($a->fresh()));
        if (AgreementPdf::available()) {
            $this->assertStringStartsWith('%PDF', (string) $maker->pdf($a->fresh()));
        }
    }
}
