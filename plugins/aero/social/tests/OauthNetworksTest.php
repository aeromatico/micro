<?php namespace Aero\Social\Tests;

use Aero\Social\Classes\Connect;
use Aero\Social\Classes\Drivers\TikTokDriver;
use Aero\Social\Classes\Drivers\XDriver;
use Aero\Social\Classes\Drivers\YoutubeDriver;
use Aero\Social\Classes\Limits;
use Aero\Social\Classes\Publisher;
use Aero\Social\Classes\Tokens;
use Aero\Social\Models\Account;
use Aero\Social\Models\App;
use Aero\Social\Models\Post;
use Aero\Social\Models\PostTarget;
use Illuminate\Support\Facades\Http;
use PluginTestCase;

class OauthNetworksTest extends PluginTestCase
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

    protected function xAccount(int $tenantId = 1, ?string $expiresIn = '7200'): Account
    {
        Connect::store('x', [['external_id' => '42', 'name' => 'Pan', 'username' => 'pan', 'avatar_url' => null]],
            ['access_token' => 'AT', 'refresh_token' => 'RT', 'expires_in' => $expiresIn], $tenantId, 5, null);

        return Account::where('tenant_id', $tenantId)->where('network', 'x')->first();
    }

    public function testStoreEncryptsTokensAndRespectsLimit(): void
    {
        $acc = $this->xAccount();
        $this->assertSame('RT', $acc->refresh_token);
        $this->assertNotSame('RT', \DB::table('aero_social_accounts')->where('id', $acc->id)->value('refresh_token'));
        $this->assertTrue($acc->token_expires_at->isFuture());

        Limits::$resolver = fn () => 1;
        $res = Connect::store('tiktok', [['external_id' => 'tt', 'name' => 'T', 'username' => null, 'avatar_url' => null]], ['access_token' => 'x'], 1, 5, null);
        $this->assertSame(['connected' => 0, 'skipped' => 1], $res);
    }

    public function testExpiredTokenIsRefreshedAndRotated(): void
    {
        config(['aero.social::x.client_id' => 'cid', 'aero.social::x.client_secret' => 'sec']);
        Http::fake(['api.x.com/2/oauth2/token' => Http::response(['access_token' => 'AT2', 'refresh_token' => 'RT2', 'expires_in' => 7200])]);

        $acc = $this->xAccount(1, '0');
        $this->assertSame('AT2', Tokens::fresh($acc));
        $this->assertSame('RT2', $acc->fresh()->refresh_token, 'X rota el refresh_token');
        Http::assertSent(fn ($r) => $r['grant_type'] === 'refresh_token' && $r['refresh_token'] === 'RT');
    }

    public function testRevokedRefreshExpiresAccountOnPublish(): void
    {
        config(['aero.social::x.client_id' => 'cid', 'aero.social::x.client_secret' => 'sec']);
        Http::fake(['api.x.com/2/oauth2/token' => Http::response(['error' => 'invalid_grant'], 400)]);

        $acc = $this->xAccount(1, '0');
        $post = new Post(['content' => 'hola', 'account_ids' => [$acc->id]]);
        $post->tenant_id = 1;
        $post->save();
        Publisher::schedule($post);

        $this->assertSame('failed', PostTarget::where('post_id', $post->id)->value('status'));
        $this->assertSame('expired', $acc->fresh()->status);
    }

    public function testTweetIsPublished(): void
    {
        Http::fake(['api.x.com/2/tweets' => Http::response(['data' => ['id' => '777']], 201)]);

        $acc = $this->xAccount();
        $post = new Post(['content' => 'Hola X', 'link' => 'https://market.com.bo', 'account_ids' => [$acc->id]]);
        $post->tenant_id = 1;
        $post->save();
        Publisher::schedule($post);

        $t = PostTarget::where('post_id', $post->id)->first();
        $this->assertSame('published', $t->status, (string) $t->error);
        $this->assertSame('https://x.com/pan/status/777', $t->permalink);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/2/tweets') && $r['text'] === "Hola X\n\nhttps://market.com.bo" && $r->hasHeader('Authorization', 'Bearer AT'));
    }

    public function testXLengthCountsLinksAs23(): void
    {
        $this->assertSame(5 + 23, XDriver::length('Hola https://muy-largo.example.com/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'));
        $post = new Post(['content' => str_repeat('a', 281)]);
        $this->assertNotEmpty((new XDriver)->validate($post, []));
    }

    public function testValidationRules(): void
    {
        $post = new Post(['content' => "Título\nresto"]);
        $jpg = ['url' => 'u', 'ext' => 'jpg', 'video' => false, 'name' => 'a.jpg'];
        $mp4 = ['url' => 'u', 'ext' => 'mp4', 'video' => true, 'name' => 'v.mp4'];

        $this->assertEmpty((new YoutubeDriver)->validate($post, [$mp4]));
        $this->assertNotEmpty((new YoutubeDriver)->validate($post, [$jpg]));
        $this->assertNotEmpty((new YoutubeDriver)->validate($post, [$mp4, $mp4]));
        $this->assertEmpty((new TikTokDriver)->validate($post, [$mp4]));
        $this->assertEmpty((new TikTokDriver)->validate($post, [$jpg, $jpg]));
        $this->assertNotEmpty((new TikTokDriver)->validate($post, [$mp4, $jpg]));
        $this->assertNotEmpty((new XDriver)->validate($post, [$mp4, $jpg]));
    }

    public function testTikTokChunksFollowLimits(): void
    {
        $this->assertSame([3_000_000, 1], TikTokDriver::chunks(3_000_000));
        [$chunk, $count] = TikTokDriver::chunks(57_000_000);
        $this->assertSame(10_000_000, $chunk);
        $this->assertSame(5, $count);   // el último absorbe el resto (17 MB < 128 MB)
    }

    public function testTikTokStatusCompletes(): void
    {
        Http::fake(['open.tiktokapis.com/v2/post/publish/status/fetch/' => Http::response([
            'data' => ['status' => 'PUBLISH_COMPLETE', 'publicaly_available_post_id' => [123]], 'error' => ['code' => 'ok'],
        ])]);

        Connect::store('tiktok', [['external_id' => 'tt', 'name' => 'T', 'username' => null, 'avatar_url' => null]], ['access_token' => 'TK', 'expires_in' => 86400], 1, 5, null);
        $acc = Account::where('network', 'tiktok')->first();
        $target = new PostTarget(['state' => ['publish_id' => 'p1']]);

        $res = (new TikTokDriver)->check($target, new Post(), $acc);
        $this->assertSame('published', $res['status']);
        $this->assertSame('https://www.tiktok.com/video/123', $res['permalink']);
    }

    public function testOwnAppKeysAreUsedForConnect(): void
    {
        $app = new App(['network' => 'x', 'client_id' => 'MI-CLIENTE', 'client_secret' => 'MI-SECRETO']);
        $app->tenant_id = 3;
        $app->save();
        $this->assertNotSame('MI-SECRETO', \DB::table('aero_social_apps')->value('client_secret'));

        $url = Connect::authorizeUrl('x', 3, 9);
        $this->assertStringContainsString('client_id=MI-CLIENTE', $url);
        $this->assertSame($app->id, session(Connect::SESSION_KEY)['app_id']);

        // Otro tenant sin llaves propias ni de plataforma: no se puede conectar.
        config(['aero.social::x.client_id' => null]);
        $this->assertFalse(Connect::ready(4, 'x'));
    }
}
