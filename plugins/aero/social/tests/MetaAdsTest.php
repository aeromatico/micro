<?php namespace Aero\Social\Tests;

use Aero\Social\Classes\Limits;
use Aero\Social\Classes\MetaAds;
use Aero\Social\Classes\MetaConnect;
use Aero\Social\Models\Account;
use Aero\Social\Models\AdAccount;
use Aero\Social\Models\AdSet;
use Aero\Social\Models\Campaign;
use Aero\Social\Models\Insight;
use Aero\Social\Models\Post;
use Aero\Social\Models\PostTarget;
use Illuminate\Support\Facades\Http;
use PluginTestCase;

class MetaAdsTest extends PluginTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        Limits::$resolver = fn () => null;
    }

    public function tearDown(): void
    {
        Limits::$resolver = null;
        parent::tearDown();
    }

    protected function adAccount(int $tenantId = 1, string $currency = 'USD'): AdAccount
    {
        return AdAccount::create([
            'tenant_id' => $tenantId, 'platform' => 'meta', 'external_id' => 'act_1', 'name' => 'Negocio',
            'currency' => $currency, 'access_token' => 'UT', 'token_expires_at' => now()->addDays(50), 'status' => 'active',
        ]);
    }

    protected function fakeGraph(): void
    {
        Http::fake([
            'graph.facebook.com/*/act_1/campaigns*' => Http::response(['data' => [
                ['id' => 'C1', 'name' => 'Ventas', 'status' => 'ACTIVE', 'effective_status' => 'ACTIVE', 'daily_budget' => '1500'],
                ['id' => 'C2', 'name' => 'Marca', 'status' => 'PAUSED', 'effective_status' => 'PAUSED'],
            ]]),
            'graph.facebook.com/*/act_1/adsets*' => Http::response(['data' => [
                ['id' => 'S2', 'campaign_id' => 'C2', 'name' => 'Set', 'status' => 'ACTIVE', 'lifetime_budget' => '20000'],
            ]]),
            'graph.facebook.com/*/act_1/insights*' => Http::response(['data' => [
                ['campaign_id' => 'C1', 'date_start' => now()->toDateString(), 'spend' => '12.50', 'impressions' => '1000', 'reach' => '800', 'clicks' => '25'],
            ]]),
            'graph.facebook.com/*' => Http::response(['success' => true, 'id' => 'NEW']),
        ]);
    }

    public function testSyncStoresCampaignsAdsetsAndInsights(): void
    {
        $this->fakeGraph();
        $acc = $this->adAccount();
        MetaAds::sync($acc);

        $this->assertSame(2, Campaign::where('ad_account_id', $acc->id)->count());
        $c1 = Campaign::where('external_id', 'C1')->first();
        $this->assertSame(1500, (int) $c1->daily_budget);
        $this->assertSame(15.0, $acc->toMajor((int) $c1->daily_budget));
        $this->assertEquals(12.5, (float) Insight::where('campaign_id', $c1->id)->value('spend'));

        // Presupuesto en el conjunto cuando la campaña no lo tiene.
        $c2 = Campaign::where('external_id', 'C2')->first();
        $this->assertInstanceOf(AdSet::class, $c2->budgetOwner());

        // Re-sincronizar no duplica.
        MetaAds::sync($acc);
        $this->assertSame(1, Insight::count());
    }

    public function testBudgetAndStatusChangesGoToTheRightObject(): void
    {
        $this->fakeGraph();
        $acc = $this->adAccount();
        MetaAds::sync($acc);

        $c2 = Campaign::where('external_id', 'C2')->first();
        MetaAds::setBudget($c2, 300);
        Http::assertSent(fn ($r) => str_ends_with(parse_url($r->url(), PHP_URL_PATH), '/S2') && $r['lifetime_budget'] == 30000);

        $c1 = Campaign::where('external_id', 'C1')->first();
        MetaAds::setStatus($c1, false);
        $this->assertSame('PAUSED', $c1->fresh()->status);
        Http::assertSent(fn ($r) => str_ends_with(parse_url($r->url(), PHP_URL_PATH), '/C1') && $r['status'] === 'PAUSED');
    }

    public function testZeroDecimalCurrency(): void
    {
        $acc = $this->adAccount(1, 'CLP');
        $this->assertSame(5000, $acc->toMinor(5000));
        $this->assertSame(500, $this->adAccount(2, 'BOB')->toMinor(5));
    }

    public function testExpiredTokenMarksAdAccount(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['code' => 190, 'message' => 'expired']], 400)]);
        $acc = $this->adAccount();

        try {
            MetaAds::sync($acc);
            $this->fail('Debía lanzar');
        } catch (\Aero\Social\Classes\SocialException $e) {
            $this->assertTrue($e->tokenInvalid);
        }
        $this->assertSame('expired', $acc->fresh()->status);
    }

    public function testBoostFacebookPostCreatesCampaignAdsetCreativeAndAd(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['id' => 'NEW'])]);
        MetaConnect::sync([['id' => '111', 'name' => 'Pan', 'access_token' => 'PT']], 1, 5);
        $page = Account::where('network', 'facebook')->first();
        $post = new Post(['content' => 'Oferta de hoy']);
        $post->tenant_id = 1;
        $post->save();
        $target = PostTarget::create([
            'tenant_id' => 1, 'post_id' => $post->id, 'account_id' => $page->id, 'network' => 'facebook',
            'status' => 'published', 'external_id' => '999',
        ]);

        $campaign = MetaAds::boost($this->adAccount(), $target, ['daily_budget' => 5, 'days' => 3, 'country' => 'bo']);

        $this->assertSame($target->id, $campaign->post_target_id);
        Http::assertSent(fn ($r) => str_ends_with(parse_url($r->url(), PHP_URL_PATH), '/act_1/adsets')
            && $r['daily_budget'] == 500 && json_decode($r['targeting'], true)['geo_locations']['countries'] === ['BO']);
        Http::assertSent(fn ($r) => str_ends_with(parse_url($r->url(), PHP_URL_PATH), '/act_1/adcreatives') && $r['object_story_id'] === '111_999');
        Http::assertSent(fn ($r) => str_ends_with(parse_url($r->url(), PHP_URL_PATH), '/act_1/ads'));
    }

    public function testBoostRejectsUnpublishedOrOtherNetworks(): void
    {
        $target = new PostTarget(['status' => 'failed', 'network' => 'facebook']);
        $this->expectException(\ApplicationException::class);
        MetaAds::boost($this->adAccount(), $target, ['daily_budget' => 5]);
    }

    public function testAdAccountsAreScopedByTenant(): void
    {
        $this->adAccount(1);
        $this->adAccount(2);
        $this->assertSame(1, AdAccount::forTenant(1)->count());
        $this->assertSame(0, AdAccount::forTenant(null)->count());
    }

    public function testStoryIdKeepsPrefixedIds(): void
    {
        $this->assertSame('1_2', MetaAds::storyId('1', '2'));
        $this->assertSame('1_2', MetaAds::storyId('1', '1_2'));
    }
}
