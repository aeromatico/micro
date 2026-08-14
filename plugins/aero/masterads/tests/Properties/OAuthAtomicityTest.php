<?php declare(strict_types=1);

namespace Aero\MasterAds\Tests\Properties;

use Aero\MasterAds\Classes\Exceptions\MetaOAuthException;
use Aero\MasterAds\Classes\Meta\MetaOAuthService;
use Aero\MasterAds\Models\MetaAccount;
use Aero\MasterAds\Models\Workspace;
use Backend\Models\User as BackendUser;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PluginTestCase;

/**
 * Property P7 — Atomicidad de OAuth.
 *
 * Validates: Property P7 / Requirements 2.5, 15.5.
 *
 * Formal statement (from design.md):
 *
 *     property_oauth_atomic: ∀ code, ∀ ws:
 *         after exchangeCode: db_state ∈ {fully_committed, fully_rolled_back}
 *
 * For every invocation of
 * {@see MetaOAuthService::exchangeCode($code, $workspaceId)}, the database
 * MUST end in exactly one of two states:
 *
 *   1. **fully_committed**
 *      A `MetaAccount` row exists with the freshly-issued long-lived
 *      token, every dispatched event has fired, and the row reflects a
 *      consistent payload.
 *
 *   2. **fully_rolled_back**
 *      NO `MetaAccount` row was created (and any pre-existing one is
 *      unchanged); no orphan rows are observable anywhere in the
 *      `aero_masterads_*` schema.
 *
 * No intermediate state — a partially-filled row, an orphan side-effect,
 * a half-rotated token — is ever observable from outside the service.
 *
 * Test strategy
 * -------------
 * The OAuth flow runs three sequential outbound HTTP calls
 * (`exchange_short_lived` → `exchange_long_lived` → `fetch_adaccounts`)
 * inside a single `DB::transaction(...)`. Using `MockHandler` we inject a
 * Guzzle transport that emits a precise sequence of canned responses and
 * blows up at one chosen step:
 *
 *   - HTTP error at the short-lived step,
 *   - HTTP error at the long-lived step,
 *   - empty `data[]` at the ad-account fetch step,
 *   - malformed JSON body at the short-lived step.
 *
 * After each induced failure the property checks the postcondition: zero
 * net change in `MetaAccount` row count AND the raised
 * {@see MetaOAuthException} carries the expected pipeline `step` in its
 * context. The success and idempotency cases assert the symmetric
 * fully_committed branch of the disjunction.
 */
class OAuthAtomicityTest extends \PluginTestCase
{
    /**
     * Configure the OAuth credentials that {@see MetaOAuthService} reads
     * from `services.master_ads_meta.*`. We set them per-test (after
     * `parent::setUp()` boots the application) so the service's
     * configuration-presence check passes and the pipeline reaches the
     * Guzzle layer where the mock transport is in control.
     */
    public function setUp(): void
    {
        parent::setUp();

        config([
            'services.master_ads_meta.app_id'      => 'test_app',
            'services.master_ads_meta.app_secret'  => 'test_secret',
            'services.master_ads_meta.redirect'    => 'https://test/cb',
            'services.master_ads_meta.api_version' => 'v19.0',
        ]);
    }

    /**
     * Build the cartesian product of failure-injection scenarios that
     * exercise every stage of the OAuth pipeline.
     *
     * Each yielded dataset carries:
     *   - `responses`   : the exact sequence of canned Guzzle responses
     *                     consumed by the three pipeline steps;
     *   - `expectedStep`: the value the raised {@see MetaOAuthException}
     *                     MUST tag in its `$context['step']` so we can
     *                     prove the failure was attributed to the
     *                     intended stage.
     *
     * @return iterable<string, array{responses: list<Response>, expectedStep: string}>
     */
    public static function failurePointProvider(): iterable
    {
        // Step 1 fails: Meta rejects the authorization code with HTTP 400.
        yield 'short_lived_token_step' => [
            'responses' => [
                new Response(400, [], json_encode(['error' => ['message' => 'invalid code']])),
            ],
            'expectedStep' => 'exchange_short_lived',
        ];

        // Step 2 fails: short-lived succeeds, long-lived 500s (Meta down).
        yield 'long_lived_token_step' => [
            'responses' => [
                new Response(200, [], json_encode(['access_token' => 'shortlived', 'expires_in' => 3600])),
                new Response(500, [], json_encode(['error' => ['message' => 'meta down']])),
            ],
            'expectedStep' => 'exchange_long_lived',
        ];

        // Step 3 fails: both token exchanges succeed but the user has no
        // ad accounts on the long-lived token (empty `data[]`).
        yield 'adaccount_fetch_step' => [
            'responses' => [
                new Response(200, [], json_encode(['access_token' => 'shortlived', 'expires_in' => 3600])),
                new Response(200, [], json_encode(['access_token' => 'longlived', 'expires_in' => 5184000])),
                new Response(200, [], json_encode(['data' => []])),
            ],
            'expectedStep' => 'fetch_adaccounts',
        ];

        // Step 1 fails differently: HTTP 200 but the body is not valid
        // JSON — covers the malformed-payload branch of `getJson`.
        yield 'malformed_short_lived_response' => [
            'responses' => [
                new Response(200, [], 'not json'),
            ],
            'expectedStep' => 'exchange_short_lived',
        ];
    }

    /**
     * Atomicity invariant — failure at any pipeline stage leaves the
     * database fully rolled back.
     *
     * For every dataset row produced by {@see self::failurePointProvider}:
     *
     *   1. Snapshot `MetaAccount::count()` before the call.
     *   2. Invoke `exchangeCode()` with a Guzzle client wired to the
     *      injected failure sequence — the call MUST raise
     *      {@see MetaOAuthException}.
     *   3. Assert the exception carries the expected step tag (proves
     *      the failure was attributed to the targeted stage and not a
     *      bypass).
     *   4. Assert the post-call count equals the pre-call count — proves
     *      no orphan `MetaAccount` row survived the partial flow.
     *
     * If the service ever stopped wrapping the pipeline in
     * `DB::transaction(...)`, the success-path `MetaAccount::updateOrCreate`
     * before the failing stage would leak a row, the count would diverge,
     * and the property would shrink down to this exact data-provider row.
     *
     * @dataProvider failurePointProvider
     * @param list<Response> $responses
     */
    public function testNoOrphanRowsOnFailureAtAnyStep(array $responses, string $expectedStep): void
    {
        $workspace            = $this->createWorkspace();
        $initialAccountCount  = MetaAccount::count();

        $client  = $this->mockGuzzleWith($responses);
        $service = new MetaOAuthService($client);

        try {
            $service->exchangeCode('abc123', $workspace->id);
            $this->fail(sprintf(
                'Expected MetaOAuthException at step "%s", but exchangeCode() returned normally',
                $expectedStep
            ));
        } catch (MetaOAuthException $e) {
            // Postcondition 1: the failure must be tagged with the
            // pipeline step we targeted — anything else means the test
            // setup is misaligned with the service contract.
            $this->assertSame(
                $expectedStep,
                $e->context['step'] ?? null,
                sprintf(
                    'Expected MetaOAuthException tagged with step "%s", got "%s"',
                    $expectedStep,
                    (string) ($e->context['step'] ?? 'null')
                )
            );

            // Postcondition 2: NO orphan rows. The MetaAccount table row
            // count MUST be identical to the snapshot taken before the
            // call — proving the transaction rolled back cleanly.
            $this->assertSame(
                $initialAccountCount,
                MetaAccount::count(),
                sprintf(
                    'Failure at step "%s" left orphan MetaAccount rows '
                    . '(before=%d, after=%d) — DB::transaction did not roll back',
                    $expectedStep,
                    $initialAccountCount,
                    MetaAccount::count()
                )
            );
        }
    }

    /**
     * Symmetric commit branch — when every pipeline stage succeeds, the
     * service MUST persist exactly one `MetaAccount` row and dispatch the
     * `aero.masterads.meta_account_connected` event with that row.
     *
     * `\Event::fake()` swaps the dispatcher singleton before invocation
     * so we can assert dispatch without running any of the production
     * listeners registered by `Plugin::boot()`.
     *
     * This test pairs with {@see self::testNoOrphanRowsOnFailureAtAnyStep}
     * to cover both halves of the property's disjunction
     * (`fully_committed` ⊕ `fully_rolled_back`).
     */
    public function testSuccessfulFlowCommitsAndDispatchesEvent(): void
    {
        $workspace = $this->createWorkspace();
        \Event::fake();

        $responses = [
            new Response(200, [], json_encode(['access_token' => 'shortlived', 'expires_in' => 3600])),
            new Response(200, [], json_encode(['access_token' => 'longlived', 'expires_in' => 5184000])),
            new Response(200, [], json_encode([
                'data' => [['id' => 'act_999', 'name' => 'Test Account', 'currency' => 'USD']],
            ])),
        ];

        $client  = $this->mockGuzzleWith($responses);
        $service = new MetaOAuthService($client);
        $account = $service->exchangeCode('abc123', $workspace->id);

        // ── Committed row reflects the response payload ─────────────────
        $this->assertNotNull($account->id, 'MetaAccount must be persisted with an id');
        $this->assertSame('act_999', $account->meta_act_id);
        $this->assertSame('USD', $account->currency);
        // Plaintext round-trip through the encryption accessor — confirms
        // the row was committed AND the token was correctly stored.
        $this->assertSame('longlived', $account->access_token);

        // ── Event fired ─────────────────────────────────────────────────
        \Event::assertDispatched('aero.masterads.meta_account_connected');
    }

    /**
     * Idempotency invariant — re-running a successful exchange for the
     * same `(workspace_id, meta_act_id)` pair MUST converge on a single
     * row (via `updateOrCreate`), never insert a duplicate.
     *
     * This is the practical guard against a user clicking the "Connect
     * with Meta" button twice in quick succession and ending up with two
     * partially-synchronised account rows that both claim the same Meta
     * ad account.
     */
    public function testIdempotentOnExistingAccount(): void
    {
        $workspace = $this->createWorkspace();
        $initialAccountCount = MetaAccount::count();

        // Each `exchangeCode` consumes exactly three responses. We hand
        // each invocation its own MockHandler-backed client so the
        // response sequences cannot bleed across calls.
        $responsesFor = static fn (): array => [
            new Response(200, [], json_encode(['access_token' => 'shortlived', 'expires_in' => 3600])),
            new Response(200, [], json_encode(['access_token' => 'longlived-1', 'expires_in' => 5184000])),
            new Response(200, [], json_encode([
                'data' => [['id' => 'act_999', 'name' => 'Test Account', 'currency' => 'USD']],
            ])),
        ];

        $firstService = new MetaOAuthService($this->mockGuzzleWith($responsesFor()));
        $first        = $firstService->exchangeCode('abc123', $workspace->id);

        // Second exchange — same workspace, same meta_act_id — must
        // **update in place** rather than insert a second row.
        $secondService = new MetaOAuthService($this->mockGuzzleWith($responsesFor()));
        $second        = $secondService->exchangeCode('abc123', $workspace->id);

        $this->assertSame(
            $initialAccountCount + 1,
            MetaAccount::count(),
            'updateOrCreate must converge on exactly one MetaAccount row '
            . 'for the same (workspace_id, meta_act_id) pair'
        );
        $this->assertSame(
            $first->id,
            $second->id,
            'The second exchange must rotate the existing row in place '
            . '(same primary key), not insert a new one'
        );
    }

    //
    // Helpers
    //

    /**
     * Create a fully-valid {@see Workspace} backed by a brand-new
     * {@see BackendUser}. Slug, login and email are derived from a
     * `uniqid()` token so the unique-index constraints on
     * `aero_masterads_workspaces.slug`, `backend_users.login` and
     * `backend_users.email` survive being driven by the data provider.
     */
    private function createWorkspace(): Workspace
    {
        $token = uniqid('', true);

        $user = BackendUser::create([
            'login'                 => 'oauth_tester_' . $token,
            'email'                 => 'oauth_tester_' . $token . '@example.test',
            'password'              => 'secret123',
            'password_confirmation' => 'secret123',
            'first_name'            => 'Test',
            'last_name'             => 'User',
            'is_activated'          => true,
        ]);

        return Workspace::create([
            'name'     => 'OAuth WS ' . $token,
            'slug'     => 'oauth-ws-' . substr(preg_replace('/[^a-z0-9]/i', '', $token), 0, 24),
            'owner_id' => $user->id,
        ]);
    }

    /**
     * Build a Guzzle client whose transport is a {@see MockHandler}
     * primed with `$responses`. Calls beyond the canned sequence raise a
     * `RuntimeException` from MockHandler, which propagates as a
     * `GuzzleException` and is wrapped by the service into a
     * {@see MetaOAuthException} — exactly the surface we exercise.
     *
     * @param list<Response> $responses
     */
    private function mockGuzzleWith(array $responses): Client
    {
        $mock    = new MockHandler($responses);
        $stack   = HandlerStack::create($mock);
        return new Client(['handler' => $stack]);
    }
}
