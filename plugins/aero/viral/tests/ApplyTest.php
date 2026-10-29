<?php namespace Aero\Viral\Tests;

use Aero\Viral\Classes\ApplicationService;
use Aero\Viral\Classes\Catalog;
use Aero\Viral\Models\Application;
use October\Rain\Exception\ApplicationException;
use October\Rain\Exception\ValidationException;
use PluginTestCase;

class ApplyTest extends PluginTestCase
{
    protected function valid(array $over = []): array
    {
        return $over + [
            'track' => 'live', 'level' => 3, 'links' => ['tiktok.com/@qa/video/1'], 'tags' => ['Live shopping', 'Inventada'],
            'description' => 'qa', 'name' => 'QA Viral', 'email' => 'qa@example.com', 'phone' => '7 123 4567',
            'department' => 'La Paz', 'city' => 'El Alto', 'password' => 'claveSegura123', 'offer' => 1, 'adult' => 1, 'terms' => 1,
        ];
    }

    public function testBolivianPhoneNormalization(): void
    {
        $this->assertSame('+59171234567', Catalog::normalizePhone('7 123 4567'));
        $this->assertSame('+59161234567', Catalog::normalizePhone('+591 61234567'));
        $this->assertSame('+59171234567', Catalog::normalizePhone('0059171234567'));
        $this->assertNull(Catalog::normalizePhone('51234567'));   // no empieza en 6/7
        $this->assertNull(Catalog::normalizePhone('712345'));
        $this->assertNull(Catalog::normalizePhone(''));
    }

    public function testUrlNormalization(): void
    {
        $this->assertSame('https://tiktok.com/x', Catalog::normalizeUrl('tiktok.com/x'));
        $this->assertSame('http://a.com', Catalog::normalizeUrl('http://a.com'));
    }

    public function testSubmitCreatesUserApplicationAndSamples(): void
    {
        $app = (new ApplicationService)->submit($this->valid(), '10.0.0.1');

        $this->assertSame('received', $app->status);
        $this->assertSame('+59171234567', $app->phone);
        $this->assertSame(['Live shopping'], $app->tags);            // la etiqueta inventada se descarta
        $this->assertNotNull($app->terms_accepted_at);
        $this->assertSame(1, $app->samples()->count());
        $this->assertSame('tiktok', $app->samples()->first()->platform);
        $this->assertSame('qa@example.com', $app->user->email);
    }

    public function testSecondSubmitReturnsExistingApplication(): void
    {
        $svc = new ApplicationService;
        $first = $svc->submit($this->valid(), '10.0.0.2');
        \Auth::logout();
        $again = $svc->submit($this->valid(['password' => 'claveSegura123']), '10.0.0.2');

        $this->assertSame($first->id, $again->id);
        $this->assertSame(1, Application::count());
    }

    public function testExistingAccountNeedsItsPassword(): void
    {
        $svc = new ApplicationService;
        $svc->submit($this->valid(), '10.0.0.3');
        \Auth::logout();

        $this->expectException(ValidationException::class);
        $svc->submit($this->valid(['password' => 'incorrecta999']), '10.0.0.3');
    }

    public function testValidationRejectsMissingLinksAndConsent(): void
    {
        try {
            (new ApplicationService)->submit($this->valid(['links' => [], 'terms' => 0, 'adult' => 0, 'phone' => '12345']), '10.0.0.4');
            $this->fail('Debió fallar la validación');
        } catch (ValidationException $e) {
            $keys = array_keys($e->getFields());
            foreach (['links', 'terms', 'adult', 'phone'] as $k) {
                $this->assertContains($k, $keys);
            }
        }
        $this->assertSame(0, Application::count());
    }

    public function testHoneypotAndRateLimit(): void
    {
        $svc = new ApplicationService;
        try {
            $svc->submit($this->valid(['website' => 'spam']), '10.0.0.5');
            $this->fail('El honeypot debió bloquear');
        } catch (ApplicationException $e) {
            $this->assertSame(0, Application::count());
        }

        $this->expectException(ApplicationException::class);
        for ($i = 0; $i < 12; $i++) {
            try { $svc->submit($this->valid(['links' => []]), '10.0.0.6'); } catch (ValidationException) {}
        }
    }

    public function testSummaryHidesInternalNotesUnlessChangesRequested(): void
    {
        $app = (new ApplicationService)->submit($this->valid(), '10.0.0.7');
        $app->review_notes = 'nota interna';
        $app->save();
        $this->assertNull(ApplicationService::summary($app->fresh())['notes']);

        $app->requestChanges(1, 'Sube un link con tu cara visible');
        $this->assertSame('Sube un link con tu cara visible', ApplicationService::summary($app->fresh())['notes']);
    }
}
