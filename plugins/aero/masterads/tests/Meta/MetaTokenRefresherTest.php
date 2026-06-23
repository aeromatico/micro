<?php declare(strict_types=1);

namespace Aero\MasterAds\Tests\Meta;

use Aero\MasterAds\Classes\Exceptions\MetaOAuthException;
use Aero\MasterAds\Classes\Meta\MetaTokenRefresher;
use Aero\MasterAds\Models\MetaAccount;
use Aero\MasterAds\Models\Workspace;
use Backend\Models\User as BackendUser;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * MetaTokenRefresherTest — covers
 * {@see \Aero\MasterAds\Classes\Meta\MetaTokenRefresher::refresh()}.
 *
 * Extends {@see \PluginTestCase} (DB migrations enabled by default) because
 * the postcondition of a successful refresh — including the
 * `expires_at > now()+30d` invariant from Requirement 2.7 — has to be
 * observed after the refresher writes through to the
 * `aero_masterads_meta_accounts` row.
 *
 * The Guzzle transport is replaced by a {@see MockHandler}; the production
 * code never opens a real socket. Configuration for
 * `services.master_ads_meta.app_id`/`.app_secret` is set in {@see setUp()}
 * so the refresher reaches the HTTP layer where the mock takes over.
 *
 * Validates: Requirements 2.7, 15.6 (master-ads spec).
 */
class MetaTokenRefresherTest extends \PluginTestCase
{
    /**
     * Always-fresh suffix used to mint unique slugs / logins / Meta act-ids
     * across the test methods of this class, so the unique-index
     * constraints on `aero_masterads_workspaces.slug`,
     * `backend_users.login`, `backend_users.email` and
     * `(workspace_id, meta_act_id)` never collide.
     */
    private static int $sequence = 0;

    /**
     * Configure the Meta app credentials AFTER {@see \PluginTestCase::setUp()}
     * has booted the application — most tests need the credentials present
     * so the refresher reaches the HTTP layer where the {@see MockHandler}
     * is in control. The credential-missing scenario explicitly clears
     * them inside its own test body.
     */
    public function setUp(): void
    {
        parent::setUp();

        config([
            'services.master_ads_meta.app_id'      => 'test_app_id',
            'services.master_ads_meta.app_secret'  => 'test_app_secret',
            'services.master_ads_meta.redirect'    => 'https://test/cb',
            'services.master_ads_meta.api_version' => 'v19.0',
        ]);
    }

    //
    // 1. Happy path — token + expires_at rotated, postcondition satisfied
    //

    /**
     * On a 200 response with `expires_in: 5184000` (60 days) the refresher
     * MUST persist the new `access_token` and set `expires_at` strictly
     * beyond `now() + 30 days` (Requirement 2.7).
     *
     * The DB row is reloaded after refresh to prove the change reached
     * storage — not just the in-memory model.
     */
    public function testRefreshUpdatesTokenAndExpiresAt(): void
    {
        $account = $this->createAccount('initial-token', Carbon::now()->addDays(5));

        $client = $this->mockGuzzleWith([
            new Response(200, [], (string) json_encode([
                'access_token' => 'newtoken',
                'token_type'   => 'bearer',
                'expires_in'   => 5184000, // 60 days, comfortably beyond the 30-day floor
            ])),
        ]);

        $refresher = new MetaTokenRefresher($client);
        $refresher->refresh($account);

        // Reload from DB to prove the rotation was actually persisted.
        $reloaded = MetaAccount::find($account->id);
        $this->assertNotNull($reloaded);
        $this->assertSame('newtoken', $reloaded->access_token, 'access_token must be rotated to the new value');
        $this->assertNotNull($reloaded->expires_at);
        $this->assertTrue(
            $reloaded->expires_at->greaterThan(Carbon::now()->addDays(30)),
            'expires_at must be > now()+30 days (Requirement 2.7)'
        );
        $this->assertNull($reloaded->last_error, 'A successful refresh must clear any prior last_error');
    }

    //
    // 2. Postcondition guard — expires_in too short must abort
    //

    /**
     * If Meta returns a lifetime that fails the >30-day postcondition,
     * the refresher MUST reject the response and throw
     * {@see MetaOAuthException} whose message mentions the
     * `"30-day postcondition"` so operators can spot the violation in
     * logs immediately.
     */
    public function testRefreshThrowsWhenLifetimeTooShort(): void
    {
        $account = $this->createAccount('initial-token', Carbon::now()->addDays(5));

        $client = $this->mockGuzzleWith([
            new Response(200, [], (string) json_encode([
                'access_token' => 'shortlived',
                'expires_in'   => 100, // ≪ 30 days → postcondition must fail
            ])),
        ]);

        $refresher = new MetaTokenRefresher($client);

        try {
            $refresher->refresh($account);
            $this->fail('Expected MetaOAuthException due to insufficient token lifetime');
        } catch (MetaOAuthException $e) {
            $this->assertStringContainsString(
                '30-day postcondition',
                $e->getMessage(),
                'Exception message must explicitly mention the 30-day postcondition for operability'
            );
        }

        // And the token MUST NOT have been overwritten with the bad value.
        $reloaded = MetaAccount::find($account->id);
        $this->assertNotSame('shortlived', $reloaded->access_token);
    }

    //
    // 3. Failure persistence + event dispatch
    //

    /**
     * On any Meta-side failure the refresher MUST
     *   (a) persist a diagnostic message into `MetaAccount.last_error`,
     *   (b) dispatch the `aero.masterads.meta_token_refresh_failed` event
     *       carrying the offending account so listeners can notify the
     *       Workspace owner (Requirement 15.6), and
     *   (c) rethrow as {@see MetaOAuthException}.
     *
     * `Event::fake()` swaps the dispatcher singleton so we can assert
     * dispatch without firing the production listeners registered by
     * `Plugin::boot()`.
     */
    public function testRefreshFailureSetsLastErrorAndFiresEvent(): void
    {
        Event::fake();

        $account = $this->createAccount('initial-token', Carbon::now()->addDays(5));

        // 500 → http_errors middleware throws ServerException → caught
        // by the refresher as GuzzleException → fail() pipeline engages.
        $client = $this->mockGuzzleWith([
            new Response(500, [], (string) json_encode(['error' => ['message' => 'meta is down']])),
        ]);

        $refresher = new MetaTokenRefresher($client);

        try {
            $refresher->refresh($account);
            $this->fail('Expected MetaOAuthException on a 500 response');
        } catch (MetaOAuthException $e) {
            // expected
        }

        $reloaded = MetaAccount::find($account->id);
        $this->assertNotNull(
            $reloaded->last_error,
            'last_error must be persisted so operators can triage the failure'
        );

        Event::assertDispatched('aero.masterads.meta_token_refresh_failed');
    }

    //
    // 4. Pre-flight guards — no current token
    //

    /**
     * If the {@see MetaAccount} has no current `access_token` to exchange
     * the refresher MUST short-circuit BEFORE issuing any HTTP request and
     * throw {@see MetaOAuthException}.
     *
     * The DB column is `NOT NULL`, so we seed the empty value through a
     * raw query that bypasses the mutator (which would otherwise rewrite
     * empty values to `NULL` and violate the schema).
     */
    public function testRefreshThrowsWhenAccountHasNoCurrentToken(): void
    {
        // Create a valid account first, then nuke its access_token at the
        // storage layer (the `access_token` column is NOT NULL, so an
        // empty string is the smallest legal "absence-of-token" value).
        $account = $this->createAccount('placeholder-token', Carbon::now()->addDays(5));

        DB::table('aero_masterads_meta_accounts')
            ->where('id', $account->id)
            ->update(['access_token' => '']);

        // Reload — the accessor maps empty raw values to `null`, which is
        // what `MetaTokenRefresher::refresh()` checks on entry.
        $reloaded = MetaAccount::find($account->id);
        $this->assertNull($reloaded->access_token, 'Sanity check: account must have no readable token');

        // No HTTP call should be issued — give the client an empty queue
        // so any accidental request fails loudly.
        $refresher = new MetaTokenRefresher($this->mockGuzzleWith([]));

        $this->expectException(MetaOAuthException::class);
        $refresher->refresh($reloaded);
    }

    //
    // 5. Pre-flight guards — Meta app credentials missing
    //

    /**
     * If `services.master_ads_meta.app_id` / `.app_secret` are not
     * configured the refresher MUST refuse to call Meta and throw
     * {@see MetaOAuthException} — protecting against an empty-credentials
     * request leaking the user's access token to a 400 response logger.
     */
    public function testRefreshThrowsWhenAppCredentialsMissing(): void
    {
        $account = $this->createAccount('initial-token', Carbon::now()->addDays(5));

        // Wipe BOTH credentials — the refresher refuses if either is empty.
        config([
            'services.master_ads_meta.app_id'     => '',
            'services.master_ads_meta.app_secret' => '',
        ]);

        // No HTTP call should be issued — empty queue so any accidental
        // request would raise from MockHandler.
        $refresher = new MetaTokenRefresher($this->mockGuzzleWith([]));

        $this->expectException(MetaOAuthException::class);
        $refresher->refresh($account);
    }

    //
    // Helpers
    //

    /**
     * Build a Guzzle client whose transport is a {@see MockHandler}
     * primed with the supplied response/exception queue. The default
     * `http_errors` middleware is left enabled so a non-2xx
     * {@see Response} is converted into the corresponding GuzzleException
     * subclass — exactly the surface the refresher catches.
     *
     * @param  array<int, Response|\Throwable> $queue
     */
    private function mockGuzzleWith(array $queue): Client
    {
        $mock  = new MockHandler($queue);
        $stack = HandlerStack::create($mock);

        return new Client([
            'handler'  => $stack,
            'base_uri' => 'https://graph.facebook.com/',
        ]);
    }

    /**
     * Create a fresh {@see Workspace} backed by a brand-new
     * {@see BackendUser} (the `owner_id` FK is constrained against
     * `backend_users`). Unique fields use a monotonic counter so each
     * test method gets a clean slate without clashing on indexes.
     */
    private function createWorkspace(): Workspace
    {
        $seq = ++self::$sequence;

        $owner = new BackendUser();
        $owner->first_name = 'Test';
        $owner->last_name = 'Owner';
        $owner->login = 'token_refresher_owner_' . $seq;
        $owner->email = 'token_refresher_owner_' . $seq . '@example.test';
        $owner->password = 'ChangeMe123!';
        $owner->password_confirmation = 'ChangeMe123!';
        $owner->is_activated = true;
        $owner->forceSave();

        $workspace = new Workspace();
        $workspace->name = 'Token Refresher WS ' . $seq;
        $workspace->slug = 'token-refresher-ws-' . $seq;
        $workspace->owner_id = $owner->id;
        $workspace->save();

        return $workspace;
    }

    /**
     * Persist a fully-valid {@see MetaAccount} for a freshly-created
     * Workspace, with the supplied initial token and expiration.
     *
     * Tokens go through the Eloquent mutator (encrypted at rest); the
     * `meta_act_id` is derived from the same monotonic counter as the
     * Workspace so the `(workspace_id, meta_act_id)` unique index holds
     * across the suite.
     */
    private function createAccount(string $token, Carbon $expiresAt): MetaAccount
    {
        $workspace = $this->createWorkspace();
        $seq = self::$sequence;

        $account = new MetaAccount();
        $account->workspace_id = $workspace->id;
        $account->meta_act_id = 'act_' . (1000 + $seq);
        $account->currency = 'USD';
        $account->access_token = $token;
        $account->expires_at = $expiresAt;
        $account->save();

        return $account;
    }
}
