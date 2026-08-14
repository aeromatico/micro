<?php declare(strict_types=1);

namespace Aero\MasterAds\Tests\Properties;

use Aero\MasterAds\Classes\Billing\UsageMeter;
use Aero\MasterAds\Classes\Engine\RecommendationApplier;
use Aero\MasterAds\Classes\Meta\MetaApiClient;
use Aero\MasterAds\Models\AiAnalysis;
use Aero\MasterAds\Models\AiProvider;
use Aero\MasterAds\Models\AppliedAction;
use Aero\MasterAds\Models\Campaign;
use Aero\MasterAds\Models\MetaAccount;
use Aero\MasterAds\Models\Recommendation;
use Aero\MasterAds\Models\Workspace;
use Backend\Models\User as BackendUser;
use Generator;
use PluginTestCase;

/**
 * Property P1 — Idempotencia de aplicación de Recommendation.
 *
 * Validates: Property P1 / Requirements 7.2, 7.12, 19.5.
 *
 * Formal statement (from design.md):
 *
 *     property_idempotent_apply: ∀ rec, ∀ N ≥ 1:
 *         apply_n_times(rec, N).meta_calls_count == 1
 *         ∧ count(AppliedAction where rec_id=rec.id ∧ success=true) ≤ 1
 *
 * Operationally:
 *
 *   For every approved {@see Recommendation} R, calling
 *   `RecommendationApplier::apply(R, userId)` N times (with N ≥ 1) must
 *   result in:
 *
 *     1. EXACTLY one effective Meta Graph "write" round-trip (the very
 *        first call performs `GET before` → `POST write` → `GET after`,
 *        for a total of 3 Meta-side requests bounded by one apply()
 *        invocation; every subsequent call MUST short-circuit before
 *        touching Meta).
 *     2. EXACTLY one {@see AppliedAction} row with `success = true`
 *        linked to R — also enforced by the
 *        `applied_actions_rec_success_unique` unique index, which would
 *        otherwise raise a `QueryException` on the second insert.
 *     3. R.status transitions to `'applied'`.
 *     4. All N invocations return the SAME `AppliedAction` instance
 *        (same primary key) — observably consistent with the
 *        short-circuit branch at the top of `apply()`.
 *
 * Strategy
 * --------
 * `RecommendationApplier` constructs its `MetaApiClient` via a protected
 * `buildMetaClient()` hook precisely so this test can swap in a
 * {@see CountingMetaApiClient} stub that:
 *
 *   - counts every call() invocation (the proxy for Meta-side work);
 *   - returns canned payloads compatible with the action_type under test
 *     so the production `apply()` lifecycle (snapshot → write → snapshot
 *     → persist) flows to completion without any HTTP traffic.
 *
 * The property is parameterised over N ∈ {1, 2, 5, 10} (data-provider) so
 * a single deviation from idempotency in the underlying applier shrinks
 * down to one specific N value.
 */
class ApplyIdempotencyTest extends PluginTestCase
{
    /**
     * Monotonically-increasing counter used to mint unique workspace
     * slugs, backend-user logins and Meta act ids across data-provider
     * iterations so the unique-index constraints on those columns survive
     * being driven multiple times within one test run.
     */
    private static int $sequence = 0;

    /**
     * The N values exercised by {@see self::testApplyIsIdempotentForNCalls()}.
     *
     * 1 covers the degenerate base case (a single apply must still
     * commit, set `status='applied'` and keep the Meta-call count
     * bounded). 2 and 5 cover the "small N" idempotency neighbourhood,
     * and 10 is large enough that a faulty implementation that re-issues
     * Meta calls would be unambiguously off by a large multiple.
     *
     * @return iterable<string, array{0: int}>
     */
    public static function repeatCountProvider(): iterable
    {
        yield 'once'        => [1];
        yield 'twice'       => [2];
        yield 'five-times'  => [5];
        yield 'ten-times'   => [10];
    }

    /**
     * Core property — for every N in {@see self::repeatCountProvider()}:
     *
     *   1. `applier->apply($rec, $userId)` invoked N times yields the
     *      SAME AppliedAction id on every call (idempotent identity).
     *   2. EXACTLY one AppliedAction row with `success = true` exists
     *      for `$rec` at the end of the loop.
     *   3. `$rec->status` settles at `'applied'`.
     *   4. The stubbed MetaApiClient is touched at most THREE times
     *      across all N invocations — the upper bound for a single
     *      apply pipeline (before snapshot + write + after snapshot).
     *      A naive implementation that forgets to short-circuit on the
     *      pre-existing AppliedAction would observe ~3·N Meta calls,
     *      blowing past this bound for any N ≥ 2.
     *
     * @dataProvider repeatCountProvider
     */
    public function testApplyIsIdempotentForNCalls(int $n): void
    {
        $rec    = $this->makeApprovedRecommendation();
        $userId = $this->makeUser()->id;

        $stub    = new CountingMetaApiClient();
        $applier = new TestableRecommendationApplier(new UsageMeter(), $stub);

        $firstActionId = null;

        for ($i = 0; $i < $n; $i++) {
            $action = $applier->apply($rec, $userId);

            $this->assertTrue(
                (bool) $action->success,
                sprintf('Call %d/%d returned an AppliedAction with success=false', $i + 1, $n)
            );

            if ($i === 0) {
                $firstActionId = $action->id;
            } else {
                $this->assertSame(
                    $firstActionId,
                    $action->id,
                    sprintf(
                        'Call %d/%d returned AppliedAction id=%d but the first call '
                        . 'returned id=%d — idempotent identity violated',
                        $i + 1,
                        $n,
                        (int) $action->id,
                        (int) $firstActionId
                    )
                );
            }
        }

        // ── Invariant 1: at most one successful AppliedAction ─────────
        // (`recommendation_id`, `success`) is the unique index name, so
        // counting on `success=true` is the direct DB witness.
        $this->assertSame(
            1,
            AppliedAction::where('recommendation_id', $rec->id)
                ->where('success', true)
                ->count(),
            sprintf(
                'Exactly 1 successful AppliedAction must exist after %d calls; '
                . 'extra rows would mean apply() bypassed the idempotency '
                . 'short-circuit at the top of the method',
                $n
            )
        );

        // ── Invariant 2: recommendation transitions to 'applied' ──────
        $rec->refresh();
        $this->assertSame(
            'applied',
            (string) $rec->status,
            sprintf('Recommendation must be flipped to status=applied after %d calls', $n)
        );

        // ── Invariant 3: Meta call count is bounded by ONE apply ──────
        // One full apply pipeline issues 3 Meta calls: GET before snapshot,
        // POST write, GET after snapshot. If apply() were not idempotent,
        // every subsequent invocation would re-trigger the full pipeline
        // and we'd see roughly 3·N calls — well over the bound for N ≥ 2.
        $this->assertLessThanOrEqual(
            3,
            $stub->callCount,
            sprintf(
                'Meta API was hit %d times across %d apply() invocations — '
                . 'idempotency violated (expected ≤ 3, the cost of a single apply pipeline)',
                $stub->callCount,
                $n
            )
        );

        // For N ≥ 1 the first apply must always reach Meta (otherwise no
        // before/after snapshot exists). The lower bound complements the
        // upper bound and rejects a degenerate "skip Meta entirely" bug.
        $this->assertGreaterThanOrEqual(
            1,
            $stub->callCount,
            'First apply must reach Meta at least once to produce a before/after snapshot'
        );
    }

    //
    // ── Fixture helpers ──────────────────────────────────────────────
    //

    /**
     * Build the full parent chain Workspace → MetaAccount → Campaign →
     * AiProvider → AiAnalysis and return a fresh approved Recommendation
     * targeting the campaign with `action_type='pause'`.
     *
     * `pause` is chosen because it is compatible with every target_type,
     * its payload is empty (no schema dependencies in this test), and its
     * effect on Meta is a single `POST {status: PAUSED}` — exercising the
     * write step of the dispatch table without coupling the property to
     * any one decimal/multiplier semantics.
     */
    private function makeApprovedRecommendation(): Recommendation
    {
        $seq   = self::nextSequence();
        $owner = $this->makeUser('rec_owner_' . $seq);

        $workspace = Workspace::create([
            'name'     => 'P1 Workspace ' . $seq,
            'slug'     => 'p1-ws-' . $seq,
            'owner_id' => $owner->id,
        ]);

        $metaAccount = MetaAccount::create([
            'workspace_id'  => $workspace->id,
            'meta_act_id'   => 'act_' . (1_000_000 + $seq),
            'name'          => 'P1 Meta Account ' . $seq,
            'currency'      => 'USD',
            'access_token'  => 'long-lived-token-' . $seq,
            // Set expiry far in the future so the production MetaApiClient
            // would not attempt a token refresh. Our stub overrides call()
            // entirely, but keeping the field realistic guards against
            // accidental coupling to the refresher in future refactors.
            'expires_at'    => now()->addDays(60),
        ]);

        $campaign = Campaign::create([
            'meta_account_id' => $metaAccount->id,
            'meta_id'         => 'meta_camp_' . $seq,
            'name'            => 'P1 Campaign ' . $seq,
            'status'          => 'ACTIVE',
            'daily_budget'    => 50.00,
        ]);

        $provider = AiProvider::create([
            'workspace_id' => $workspace->id,
            'name'         => 'P1 Provider ' . $seq,
            'driver'       => 'openrouter',
            'model'        => 'openai/gpt-4o',
            'api_key'      => 'sk-p1-' . $seq,
            'is_default'   => true,
        ]);

        $analysis = AiAnalysis::create([
            'workspace_id'   => $workspace->id,
            'ai_provider_id' => $provider->id,
            'target_type'    => 'campaign',
            'target_id'      => $campaign->id,
            'status'         => 'success',
        ]);

        return Recommendation::create([
            'ai_analysis_id' => $analysis->id,
            'action_type'    => 'pause',
            'severity'       => 'medium',
            'status'         => 'approved',
            'rationale'      => 'Spend without conversions — pause for review.',
            'payload'        => [],
        ]);
    }

    /**
     * Create a fresh BackendUser with unique login/email so the FK rule
     * on `applied_actions.applied_by` is always satisfied.
     */
    private function makeUser(?string $loginPrefix = null): BackendUser
    {
        $seq    = self::nextSequence();
        $prefix = $loginPrefix ?? 'p1_user_' . $seq;
        $unique = $prefix . '_' . $seq;

        return BackendUser::create([
            'login'                 => $unique,
            'email'                 => $unique . '@example.test',
            'password'              => 'secret123',
            'password_confirmation' => 'secret123',
            'first_name'            => 'P1',
            'last_name'             => 'Tester',
            'is_activated'          => true,
        ]);
    }

    /**
     * Process-local monotonic counter — keeps slugs, logins, Meta act
     * ids and Meta entity ids unique across the four data-provider rows
     * driven into the same test method.
     */
    private static function nextSequence(): int
    {
        return ++self::$sequence;
    }
}

/**
 * CountingMetaApiClient — Test-only stub that extends {@see MetaApiClient}
 * to record every `call()` invocation without touching the network.
 *
 * Skips parent::__construct intentionally: the production constructor
 * builds a Guzzle Client and stores a MetaAccount, neither of which this
 * stub needs because every code path the applier exercises against it is
 * overridden here.
 *
 * Returns canned payloads that satisfy the `pause` dispatch contract:
 *
 *   - GET requests return a budget/status snapshot — used as
 *     `before_state` and `after_state` JSON in the AppliedAction row.
 *   - Any other verb returns `['success' => true]` — used as the
 *     `meta_response` audit blob.
 */
class CountingMetaApiClient extends MetaApiClient
{
    /**
     * Monotonically-incrementing tally of every `call()` invocation
     * issued through this stub since instantiation. The property test
     * reads this directly to verify that idempotency keeps the count
     * bounded by a single apply pipeline (≤ 3).
     */
    public int $callCount = 0;

    /**
     * @noinspection PhpMissingParentConstructorInspection — intentional:
     * the stub never delegates HTTP work and therefore needs neither a
     * Guzzle client nor a MetaAccount handle. PHP allows skipping
     * parent::__construct() as long as the parent declares no abstract
     * constructor — true for {@see MetaApiClient}.
     */
    public function __construct()
    {
        // No-op: see class docblock.
    }

    /**
     * Record the call and return a canned response shaped to the verb.
     *
     * @inheritDoc
     */
    public function call(string $method, string $endpoint, array $params = []): array
    {
        $this->callCount++;

        if (strtoupper($method) === 'GET') {
            // Match the FIELDS_BUDGET snapshot shape used by `pause`,
            // `resume`, `adjust_budget` and `scale`.
            return [
                'daily_budget'    => 5000,
                'lifetime_budget' => null,
                'status'          => 'ACTIVE',
            ];
        }

        // POST / DELETE — Meta's typical success payload.
        return ['success' => true];
    }

    /**
     * No-op generator override: the applier never paginates, but the
     * parent signature must be preserved so subclassing is type-safe.
     *
     * @inheritDoc
     */
    public function getPaginated(string $endpoint, array $params = []): Generator
    {
        yield from [];
    }
}

/**
 * TestableRecommendationApplier — Production applier with the
 * `buildMetaClient` hook redirected at a {@see CountingMetaApiClient}
 * stub. Every other aspect of the production pipeline (idempotency
 * check, target resolution, action-type validation, snapshot/dispatch,
 * AppliedAction persistence, status transition, event dispatch) runs
 * unchanged — that is precisely what gives the property its bite.
 */
class TestableRecommendationApplier extends RecommendationApplier
{
    /**
     * @param  UsageMeter             $meter       Forwarded to the
     *                                             production constructor.
     * @param  CountingMetaApiClient  $clientStub  Counting stub returned by
     *                                             {@see self::buildMetaClient()}.
     */
    public function __construct(
        UsageMeter $meter,
        private readonly CountingMetaApiClient $clientStub,
    ) {
        parent::__construct($meter);
    }

    /**
     * Override the protected production hook so every apply() in this
     * test routes through the counting stub instead of Guzzle.
     *
     * @inheritDoc
     */
    protected function buildMetaClient(MetaAccount $metaAccount): MetaApiClient
    {
        return $this->clientStub;
    }
}
