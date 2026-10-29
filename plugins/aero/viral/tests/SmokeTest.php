<?php namespace Aero\Viral\Tests;

use Aero\Viral\Models\Application;
use Aero\Viral\Models\KycCheck;
use Aero\Viral\Models\Sample;
use PluginTestCase;

class SmokeTest extends PluginTestCase
{
    protected function makeApp(): Application
    {
        return Application::create(['user_id' => 1, 'track' => 'shorts', 'level' => 2]);
    }

    public function testMigrationsCreateTables(): void
    {
        foreach (['aero_viral_applications', 'aero_viral_samples', 'aero_viral_kyc_checks'] as $t) {
            $this->assertTrue(\Schema::hasTable($t), "Falta {$t}");
        }
    }

    public function testNavigationRequiresDeclaredPermissions(): void
    {
        $plugin = new \Aero\Viral\Plugin($this->app);
        $declared = array_keys($plugin->registerPermissions());
        foreach ($plugin->registerNavigation() as $item) {
            foreach ($item['permissions'] ?? [] as $p) {
                $this->assertContains($p, $declared);
            }
        }
    }

    public function testCannotApproveWithoutVerifiedIdentity(): void
    {
        $app = $this->makeApp();
        $this->assertNotEmpty($app->uuid);
        $this->expectException(\RuntimeException::class);
        $app->approve(1);
    }

    public function testKycVerifyUnlocksApprovalAndFiresEvents(): void
    {
        $fired = [];
        \Event::listen('aero.viral.*', function ($event) use (&$fired) { $fired[] = $event; });

        $app = $this->makeApp();
        $kyc = KycCheck::create(['application_id' => $app->id, 'user_id' => 1, 'driver' => 'manual']);
        $kyc->doc_number = '1234567 LP';
        $kyc->save();
        $this->assertNotSame('1234567 LP', $kyc->getRawOriginal('doc_number_enc'));
        $this->assertSame('1234567 LP', $kyc->fresh()->doc_number);

        $kyc->verify(5);
        $this->assertNotNull($kyc->fresh()->purge_at);
        $app = $app->fresh();
        $this->assertSame('verified', $app->kyc_status);

        $app->approve(5);
        $this->assertSame('approved', $app->fresh()->status);
        $this->assertContains('aero.viral.kycVerified', $fired);
        $this->assertContains('aero.viral.applicationApproved', $fired);
    }

    public function testPlatformDetection(): void
    {
        $this->assertSame('tiktok', Sample::detectPlatform('https://www.tiktok.com/@a/video/1'));
        $this->assertSame('youtube', Sample::detectPlatform('https://youtu.be/x'));
        $this->assertSame('other', Sample::detectPlatform('https://miweb.com/reel'));
        $this->assertSame('other', Sample::detectPlatform('https://nottiktok.com/x'));
    }
}
