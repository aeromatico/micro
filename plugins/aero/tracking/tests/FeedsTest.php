<?php namespace Aero\Tracking\Tests;

use Aero\Tracking\Classes\Feeds\FeedGoneException;
use Aero\Tracking\Classes\Feeds\FeedRegistry;
use Aero\Tracking\Classes\Feeds\FeedService;
use Aero\Tracking\Models\Feed;
use Carbon\Carbon;
use PluginTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

class FeedsTest extends PluginTestCase
{
    protected const UUID = '6b64fd95-4421-41b7-a3b5-b75ce20fb34c';
    protected const URL = 'https://web-apps.pedidosya.com/shared-order-state/' . self::UUID;

    /** El primer stub que coincide gana: hay que reiniciar la fábrica para re-simular. */
    protected function resetHttp(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
    }

    protected function fakePeya(string $phase = 'on_the_way', float $lat = -17.3965, ?string $ts = null): void
    {
        $this->resetHttp();
        $state = match ($phase) {
            'confirmed'  => ['confirmed' => true],
            'on_the_way' => ['confirmed' => true, 'pickedUp' => true, 'nearOriginOrAfter' => true],
            'delivered'  => ['confirmed' => true, 'pickedUp' => true, 'delivered' => true],
            'cancelled'  => ['cancelled' => true],
        };

        Http::fake([
            '*/shared-order-state/*' => Http::response('<html></html>', 200, ['Set-Cookie' => 'soid=abc; Path=/']),
            '*/order-tracking' => Http::response([
                'orderId' => 2314622183, 'state' => $state,
                'progress' => ['orderTrackingRevampV2' => [
                    'eta' => ['value' => '11:35 - 11:55', 'title' => ['label' => 'A tiempo']],
                    'orderState' => ['message' => 'En camino'],
                ]],
                'orderDetails' => ['vendorName' => 'Market', 'productsLabel' => '8 productos'],
            ]),
            '*/order-location' => Http::response([
                'waypoints' => ['origin' => ['latitude' => -17.37, 'longitude' => -66.15], 'destination' => ['latitude' => -17.39, 'longitude' => -66.18]],
                'rider' => ['last_location' => ['latitude' => $lat, 'longitude' => -66.17, 'timestamp' => $ts ?? now()->toIso8601String()]],
                'peya_config' => ['update_rate_millis' => 10000],
            ]),
        ]);
    }

    public function testMigrationsCreateTables(): void
    {
        foreach (['aero_tracking_feeds', 'aero_tracking_feed_events'] as $t) {
            $this->assertTrue(\Schema::hasTable($t), "Falta {$t}");
        }
    }

    public function testDriverOnlyAcceptsPedidosYaHost(): void
    {
        $this->assertNotNull(FeedRegistry::detect(self::URL));
        $this->assertNotNull(FeedRegistry::detect(self::URL . '?x=1'));
        $this->assertNull(FeedRegistry::detect('https://evil.example/shared-order-state/' . self::UUID));
        $this->assertNull(FeedRegistry::detect('http://web-apps.pedidosya.com/shared-order-state/' . self::UUID));
        $this->assertNull(FeedRegistry::detect('https://web-apps.pedidosya.com.evil.io/shared-order-state/' . self::UUID));
        $this->assertNull(FeedRegistry::detect('https://web-apps.pedidosya.com/shared-order-state/not-a-uuid'));
    }

    public function testAddCreatesJobAssetAndMovesJobWithOrder(): void
    {
        Queue::fake();
        $this->fakePeya('on_the_way');

        $feed = FeedService::add(7, self::URL, 'Mi pedido');

        $this->assertSame('active', $feed->status);
        $this->assertSame('on_the_way', $feed->phase);
        $this->assertSame('in_progress', $feed->job->status);
        $this->assertSame(7, $feed->asset->tenant_id);
        $this->assertEquals(-17.3965, (float) $feed->asset->fresh()->last_lat);
        $this->assertTrue($feed->events()->where('type', 'started')->exists());
        Queue::assertPushed(\Aero\Tracking\Jobs\PollFeed::class);
        $this->assertNotNull($feed->session_state);
        $this->assertStringNotContainsString('soid=abc', $feed->session_state, 'La cookie debe ir cifrada');
    }

    public function testDeliveredClosesFeedAndJob(): void
    {
        Queue::fake();
        $this->fakePeya('on_the_way');
        $feed = FeedService::add(7, self::URL);

        $this->fakePeya('delivered');
        FeedService::poll($feed);

        $feed->refresh();
        $this->assertSame('completed', $feed->status);
        $this->assertSame('completed', $feed->job->fresh()->status);
        $this->assertFalse((bool) $feed->asset->fresh()->is_active);
        $this->assertTrue($feed->events()->where('type', 'phase_changed')->exists());
        $this->assertTrue($feed->events()->where('type', 'finished')->exists());
    }

    public function testSmallMovementIsNotStoredButBigOneIs(): void
    {
        Queue::fake();
        $this->fakePeya('on_the_way', -17.3965, now()->subSeconds(20)->toIso8601String());
        $feed = FeedService::add(7, self::URL);
        $count = fn () => \Aero\Tracking\Models\Position::where('asset_id', $feed->asset_id)->count();
        $this->assertSame(1, $count());

        $this->fakePeya('on_the_way', -17.39651, now()->subSeconds(10)->toIso8601String());   // ~1 m
        FeedService::poll($feed->fresh());
        $this->assertSame(1, $count());

        $this->fakePeya('on_the_way', -17.3990, now()->toIso8601String());                    // ~280 m
        FeedService::poll($feed->fresh());
        $this->assertSame(2, $count());
    }

    public function testGoneLinkExpiresFeed(): void
    {
        Queue::fake();
        $this->fakePeya('on_the_way');
        $feed = FeedService::add(7, self::URL);

        $this->resetHttp();
        Http::fake(['*' => Http::response('', 404)]);
        FeedService::poll($feed);

        $this->assertSame('expired', $feed->fresh()->status);
    }

    public function testFinishedOrderIsRejectedOnAdd(): void
    {
        $this->fakePeya('delivered');
        $this->expectException(\InvalidArgumentException::class);
        FeedService::add(7, self::URL);
    }

    public function testDuplicateActiveFeedIsRejectedPerTenantOnly(): void
    {
        Queue::fake();
        $this->fakePeya('on_the_way');
        FeedService::add(7, self::URL);
        FeedService::add(8, self::URL);   // otro tenant puede seguir el mismo enlace

        $this->expectException(\InvalidArgumentException::class);
        FeedService::add(7, self::URL);
    }

    public function testNavigationAndPermissionsAreCoherent(): void
    {
        $plugin = new \Aero\Tracking\Plugin($this->app);
        $declared = array_keys($plugin->registerPermissions());
        foreach ($plugin->registerNavigation() as $item) {
            foreach (($item['sideMenu'] ?? []) as $side) {
                foreach (($side['permissions'] ?? []) as $p) {
                    $this->assertContains($p, $declared);
                }
            }
        }
        $this->assertArrayHasKey('feeds', $plugin->registerNavigation()['tracking']['sideMenu']);
    }
}
