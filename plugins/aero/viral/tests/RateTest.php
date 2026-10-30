<?php namespace Aero\Viral\Tests;

use Aero\Viral\Classes\ApplicationService;
use Aero\Viral\Classes\Catalog;
use Aero\Viral\Models\Application;
use Illuminate\Validation\ValidationException;
use PluginTestCase;

class RateTest extends PluginTestCase
{
    public function testShortsNoobIsFixedAndIgnoresClientAmount(): void
    {
        $this->assertSame(['model' => 'per_second', 'amount' => 3.0], Catalog::resolveRate('shorts', 1, 'per_hour', '999'));
    }

    public function testShortsEnRachaHasNoAmountUntilReview(): void
    {
        $this->assertSame(['model' => 'per_second', 'amount' => null], Catalog::resolveRate('shorts', 2, null, '50'));
    }

    public function testShortsProAndViralChooseTheirOwn(): void
    {
        $this->assertSame(['model' => 'per_second', 'amount' => 12.5], Catalog::resolveRate('shorts', 3, null, '12.5'));
        $this->assertArrayHasKey('errors', Catalog::resolveRate('shorts', 4, null, ''));
        $this->assertArrayHasKey('errors', Catalog::resolveRate('shorts', 3, null, '0'));
    }

    public function testLiveRequiresModelAndAmount(): void
    {
        foreach ([1, 2, 3, 4] as $level) {
            $this->assertArrayHasKey('errors', Catalog::resolveRate('live', $level, null, '10'));
            $this->assertArrayHasKey('errors', Catalog::resolveRate('live', $level, 'per_hour', ''));
            $this->assertSame(['model' => 'per_hour', 'amount' => 80.0], Catalog::resolveRate('live', $level, 'per_hour', '80'));
        }
        $this->assertSame(['model' => 'commission_fixed', 'amount' => 5.0], Catalog::resolveRate('live', 1, 'commission_fixed', '5'));
        $this->assertArrayHasKey('errors', Catalog::resolveRate('live', 1, 'commission_percent', '101'));
        $this->assertArrayHasKey('errors', Catalog::resolveRate('live', 1, 'por_segundo', '5'));
    }

    public function testEditHasNoUpfrontRate(): void
    {
        $this->assertSame(['model' => 'per_project', 'amount' => null], Catalog::resolveRate('edit', 3, 'per_hour', '70'));
    }

    public function testLabels(): void
    {
        $this->assertSame('Bs 3 por segundo', Catalog::rateLabel('per_second', 3.0));
        $this->assertSame('Bs 3 a 10 por segundo (se define al revisar)', Catalog::rateLabel('per_second', null));
        $this->assertSame('12,5% por venta', Catalog::rateLabel('commission_percent', 12.5));
        $this->assertSame('Bs 80 por hora', Catalog::rateLabel('per_hour', 80));
    }

    public function testRateStoredOnSubmitAndMissingRateRejected(): void
    {
        $base = ['track' => 'live', 'level' => 2, 'links' => ['tiktok.com/@qa/video/1'], 'tags' => [], 'name' => 'QA Viral',
            'email' => 'rate@example.com', 'phone' => '71234567', 'department' => 'La Paz', 'city' => 'El Alto',
            'password' => 'claveSegura123', 'adult' => 1, 'terms' => 1];
        try {
            (new ApplicationService)->submit($base, null);
            $this->fail('Debió rechazar la falta de tarifa');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('rate_model', $e->errors());
        }
        $app = (new ApplicationService)->submit($base + ['rate_model' => 'per_hour', 'rate_amount' => '90'], null);
        $this->assertSame('per_hour', $app->rate_model);
        $this->assertEquals(90, $app->rate_amount);
        $this->assertSame('Bs 90 por hora', ApplicationService::summary($app)['rate_label']);
        $this->assertInstanceOf(Application::class, $app);
    }

    public function testCareerPromotionAndViralStaysManual(): void
    {
        $app = Application::create(['user_id' => 1, 'track' => 'shorts', 'level' => 1, 'rate_model' => 'per_second', 'rate_amount' => 3]);
        $this->assertFalse($app->addPositiveFeedback());
        $this->assertFalse($app->addPositiveFeedback());
        $this->assertTrue($app->addPositiveFeedback());          // 3.º → Casual
        $this->assertSame(2, (int) $app->level);
        $this->assertSame(0, (int) $app->positive_feedbacks);
        $this->assertNull($app->rate_amount);                    // la fija la revisión

        for ($i = 0; $i < 9; $i++) {
            $this->assertFalse($app->addPositiveFeedback());
        }
        $this->assertTrue($app->addPositiveFeedback());          // 10.º → Pro
        $this->assertSame(3, (int) $app->fresh()->level);

        for ($i = 0; $i < 30; $i++) {
            $app->addPositiveFeedback();
        }
        $this->assertSame(3, (int) $app->fresh()->level);        // Pro no sube solo; Viral lo decide el equipo
        $this->assertSame(30, (int) $app->fresh()->positive_feedbacks);
    }
}
