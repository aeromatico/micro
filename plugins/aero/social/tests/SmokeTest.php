<?php namespace Aero\Social\Tests;

use Aero\Social\Classes\Drivers\FacebookDriver;
use Aero\Social\Classes\Drivers\InstagramDriver;
use Aero\Social\Classes\MetaConnect;
use Aero\Social\Classes\Publisher;
use Aero\Social\Models\Account;
use Aero\Social\Models\Post;
use Aero\Social\Models\PostTarget;
use Illuminate\Support\Facades\Http;
use PluginTestCase;

class SmokeTest extends PluginTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        // Sin tablas de Sites en SQLite: sin límite salvo que el test lo fije.
        \Aero\Social\Classes\Limits::$resolver = fn () => null;
    }

    public function tearDown(): void
    {
        \Aero\Social\Classes\Limits::$resolver = null;
        parent::tearDown();
    }

    protected function pages(): array
    {
        return [[
            'id' => '111', 'name' => 'Panadería', 'access_token' => 'PAGE-TOKEN',
            'picture' => ['data' => ['url' => 'https://x/p.jpg']],
            'instagram_business_account' => ['id' => '999', 'username' => 'panaderia'],
        ]];
    }

    protected function fbAccount(int $tenantId = 1): Account
    {
        MetaConnect::sync($this->pages(), $tenantId, 5);

        return Account::where('tenant_id', $tenantId)->where('network', 'facebook')->first();
    }

    protected function makePost(int $tenantId, array $accountIds, string $content = 'Hola'): Post
    {
        $post = new Post(['content' => $content, 'account_ids' => $accountIds]);
        $post->tenant_id = $tenantId;
        $post->save();

        return $post;
    }

    public function testMigrationsCreateTables(): void
    {
        foreach (['aero_social_accounts', 'aero_social_posts', 'aero_social_post_targets'] as $table) {
            $this->assertTrue(\Schema::hasTable($table), "Falta la tabla {$table}");
        }
    }

    public function testNavigationRequiresDeclaredPermissions(): void
    {
        $plugin = new \Aero\Social\Plugin($this->app);
        $declared = array_keys($plugin->registerPermissions());

        foreach ($plugin->registerNavigation() as $item) {
            foreach (array_merge($item['permissions'] ?? [], ...array_column($item['sideMenu'] ?? [], 'permissions')) as $p) {
                $this->assertContains($p, $declared, "El menú exige un permiso no declarado: {$p}");
            }
        }
    }

    public function testSyncCreatesPageAndInstagramWithEncryptedToken(): void
    {
        $counts = MetaConnect::sync($this->pages(), 1, 5);
        $this->assertSame(['facebook' => 1, 'instagram' => 1, 'skipped' => 0], $counts);

        $fb = Account::where('network', 'facebook')->first();
        $ig = Account::where('network', 'instagram')->first();
        $this->assertSame($fb->id, $ig->parent_id);
        $this->assertSame('PAGE-TOKEN', $ig->access_token);
        $this->assertNotSame('PAGE-TOKEN', \DB::table('aero_social_accounts')->where('id', $fb->id)->value('access_token'));

        // Reconectar no duplica.
        MetaConnect::sync($this->pages(), 1, 5);
        $this->assertSame(2, Account::where('tenant_id', 1)->count());
    }

    public function testPlanLimitCapsNewAccountsButNotReconnects(): void
    {
        \Aero\Social\Classes\Limits::$resolver = fn () => 1;
        try {
            $counts = MetaConnect::sync($this->pages(), 1, 5);
            $this->assertSame(['facebook' => 1, 'instagram' => 0, 'skipped' => 1], $counts);

            // Reconectar la misma página no consume cupo.
            $again = MetaConnect::sync($this->pages(), 1, 5);
            $this->assertSame(1, $again['facebook']);
            $this->assertSame(1, \Aero\Social\Classes\Limits::used(1));
        } finally {
            \Aero\Social\Classes\Limits::$resolver = fn () => null;
        }
    }

    public function testTenantIsolation(): void
    {
        MetaConnect::sync($this->pages(), 1, 5);
        MetaConnect::sync($this->pages(), 2, 6);

        $this->assertSame(2, Account::forTenant(1)->count());
        $this->assertSame(2, Account::forTenant(2)->count());
        $this->assertSame(0, Account::forTenant(null)->count(), 'Sin tenant no se ve nada');

        // Un post del tenant 2 no puede usar cuentas del tenant 1.
        $foreign = Account::forTenant(1)->where('network', 'facebook')->first();
        $post = $this->makePost(2, [$foreign->id]);
        $this->assertSame([], Publisher::accounts($post));
        $this->assertNotEmpty(Publisher::validate($post));
    }

    public function testDriverValidation(): void
    {
        $post = new Post(['content' => 'x']);
        $png = ['url' => 'u', 'ext' => 'png', 'video' => false, 'name' => 'a.png'];
        $mp4 = ['url' => 'u', 'ext' => 'mp4', 'video' => true, 'name' => 'v.mp4'];

        $this->assertNotEmpty((new InstagramDriver)->validate($post, []), 'IG exige archivo');
        $this->assertNotEmpty((new InstagramDriver)->validate($post, [$png]), 'IG solo JPG');
        $this->assertEmpty((new InstagramDriver)->validate($post, [$mp4]));
        $this->assertNotEmpty((new FacebookDriver)->validate($post, [$mp4, $png]), 'FB no mezcla video y fotos');
        $this->assertEmpty((new FacebookDriver)->validate($post, []));
    }

    public function testPublishTextToFacebook(): void
    {
        Http::fake(['graph.facebook.com/*/111/feed' => Http::response(['id' => '111_222'])]);

        $fb = $this->fbAccount();
        $post = $this->makePost(1, [$fb->id]);
        Publisher::schedule($post);   // cola sync: publica en el acto

        $target = PostTarget::where('post_id', $post->id)->first();
        $this->assertSame('published', $target->status, (string) $target->error);
        $this->assertSame('https://www.facebook.com/111_222', $target->permalink);
        $this->assertSame('published', $post->fresh()->status);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/111/feed') && $r['message'] === 'Hola' && isset($r['appsecret_proof']) === (bool) config('aero.social::meta.app_secret'));
    }

    public function testRevokedTokenFailsAndExpiresAccount(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['code' => 190, 'message' => 'Session expired']], 400)]);

        $fb = $this->fbAccount();
        $post = $this->makePost(1, [$fb->id]);
        Publisher::schedule($post);

        $target = PostTarget::where('post_id', $post->id)->first();
        $this->assertSame('failed', $target->status);
        $this->assertStringContainsString('Vuelve a conectar', $target->error);
        $this->assertSame('expired', $fb->fresh()->status);
        $this->assertSame('failed', $post->fresh()->status);
    }

    public function testScheduledPostWaitsAndCanBeCancelled(): void
    {
        Http::fake();
        $fb = $this->fbAccount();
        $post = $this->makePost(1, [$fb->id]);
        $post->scheduled_at = now()->addDay();
        $post->save();

        Publisher::schedule($post);
        $this->assertSame('scheduled', $post->fresh()->status);
        $this->assertSame(0, Publisher::dispatchDue());
        Http::assertNothingSent();

        $this->assertSame(1, Publisher::cancel($post->fresh()));
        $this->assertSame('draft', $post->fresh()->status);
        $this->assertSame('cancelled', PostTarget::where('post_id', $post->id)->value('status'));
    }

    public function testInstagramContainerIsPublishedWhenFinished(): void
    {
        Http::fake([
            'graph.facebook.com/*/C1?*'           => Http::response(['status_code' => 'FINISHED']),
            'graph.facebook.com/*/999/media_publish' => Http::response(['id' => 'M1']),
            'graph.facebook.com/*/M1?*'           => Http::response(['permalink' => 'https://instagram.com/p/abc']),
        ]);

        MetaConnect::sync($this->pages(), 1, 5);
        $ig = Account::where('network', 'instagram')->first();
        $post = $this->makePost(1, [$ig->id]);
        $target = new PostTarget(['state' => ['stage' => 'container', 'container' => 'C1']]);

        $result = (new InstagramDriver)->check($target, $post, $ig);
        $this->assertSame('published', $result['status']);
        $this->assertSame('https://instagram.com/p/abc', $result['permalink']);
    }
}
