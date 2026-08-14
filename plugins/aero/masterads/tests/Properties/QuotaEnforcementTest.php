<?php declare(strict_types=1);

namespace Aero\MasterAds\Tests\Properties;

use Aero\MasterAds\Classes\Ai\AiProviderInterface;
use Aero\MasterAds\Classes\Ai\AiProviderResolver;
use Aero\MasterAds\Classes\Ai\AiResponse;
use Aero\MasterAds\Classes\Ai\PromptBuilder;
use Aero\MasterAds\Classes\Ai\RecommendationValidator;
use Aero\MasterAds\Classes\Ai\ResponseParser;
use Aero\MasterAds\Classes\Billing\PlanLimiter;
use Aero\MasterAds\Classes\Billing\UsageMeter;
use Aero\MasterAds\Classes\Engine\MetricsAggregator;
use Aero\MasterAds\Classes\Engine\RecommendationEngine;
use Aero\MasterAds\Classes\Exceptions\QuotaExceededException;
use Aero\MasterAds\Models\AiProvider;
use Aero\MasterAds\Models\Campaign;
use Aero\MasterAds\Models\Insight;
use Aero\MasterAds\Models\MetaAccount;
use Aero\MasterAds\Models\Plan;
use Aero\MasterAds\Models\Subscription;
use Aero\MasterAds\Models\UsageRecord;
use Aero\MasterAds\Models\Workspace;
use Backend\Models\User as BackendUser;
use PluginTestCase;

/**
 * Property P3 — Conservación de cuota / Quota Enforcement.
 *
 * Validates: Property P3 / Requirements 6.3, 9.3, 9.4.
 *
 * Formal statement (from design.md):
 *
 *     property_quota_enforcement:
 *         ∀ Subscription S, ∀ billing period P =[S.period_start, S.period_end]:
 *             count(UsageRecord
 *                   WHERE subscription_id = S.id
 *                     AND metric          = 'analysis'
 *                     AND recorded_at     ∈ P)
 *               ≤ S.plan.max_analyses_month
 *
 * Operationally: the {@see PlanLimiter::canRunAnalysis()} gate at the top
 * of {@see RecommendationEngine::analyze()} MUST reject the
 * (cap + 1)-th attempt with {@see QuotaExceededException} BEFORE any
 * `Ai_Analysis` row is created and BEFORE any new `Usage_Record` is
 * appended — guaranteeing the conservation law above holds at every
 * intermediate step, not just at the end of the run.
 *
 * Strategy
 * --------
 * For a grid of `(planCap, attemptCount)` pairs that bracket the
 * "under-cap / exact / over-cap" regimes, drive the engine
 * `attemptCount` times against a single Workspace whose Plan caps
 * `max_analyses_month = planCap`. We tally:
 *
 *   - `$successes`     — invocations that returned an Ai_Analysis;
 *   - `$quotaExceeded` — invocations that raised {@see QuotaExceededException}.
 *
 * Then we assert four invariants:
 *
 *   1. The number of analysis Usage_Records inside the Subscription's
 *      billing window NEVER exceeds `planCap` (the property itself).
 *   2. The number of successful invocations equals `min(attemptCount, planCap)`
 *      — the gate behaves as a saturating counter, not as a no-op.
 *   3. Every other invocation raises {@see QuotaExceededException} with
 *      `metric = 'analysis'` (the limiter signals which dimension was
 *      breached, not just "some quota").
 *   4. (implied by 1+2) Successful invocations leave EXACTLY one
 *      Usage_Record each — the meter is append-only and never skipped.
 *
 * The LLM boundary is the only collaborator that is faked: a self-contained
 * anonymous {@see AiProviderInterface} returns a deterministic
 * {@see AiResponse} so each successful invocation walks the entire engine
 * pipeline (insight history check, prompt build, provider call,
 * Recommendation persistence, usage metering) without ever touching the
 * network. Every other collaborator — {@see PlanLimiter},
 * {@see UsageMeter}, {@see PromptBuilder}, {@see ResponseParser},
 * {@see RecommendationValidator}, {@see MetricsAggregator} — runs as
 * production code, so the property exercises the exact code path that
 * ships.
 */
class QuotaEnforcementTest extends PluginTestCase
{
    /**
     * Parameter grid for {@see self::testUsageRecordsNeverExceedCap()}.
     *
     * Each row is a `(planCap, attemptCount)` tuple deliberately chosen to
     * sweep three regimes around the cap boundary so a single deviation
     * shrinks to one specific tuple:
     *
     *   - under cap            — engine MUST never reject;
     *   - exactly at cap       — engine MUST run all attempts but bill them all;
     *   - well past cap        — engine MUST reject the surplus and the
     *                            UsageRecord count MUST stay bounded by `planCap`.
     *
     * The tightest case (`cap-1-attempts-10`) exercises the "one-success
     * then nine-rejections" path that catches off-by-one bugs in the gate
     * (e.g. `count <= max` vs `count < max`).
     *
     * @return iterable<string, array{0: int, 1: int}>
     */
    public static function planCapProvider(): iterable
    {
        yield 'cap-3-attempts-2'  => [3, 2];   // under cap — no rejections expected
        yield 'cap-3-attempts-3'  => [3, 3];   // exact — every attempt succeeds, cap exhausted
        yield 'cap-3-attempts-5'  => [3, 5];   // 2 over cap — surplus must be rejected
        yield 'cap-1-attempts-10' => [1, 10];  // tight cap — 1 success, 9 rejections
        yield 'cap-10-attempts-15' => [10, 15]; // larger cap, 5 over — same pattern at scale
    }

    /**
     * Core property — for every `(planCap, attemptCount)` in
     * {@see self::planCapProvider()}:
     *
     *   1. `count(UsageRecord(metric='analysis') in period) ≤ planCap`
     *      (P3 itself: the conservation law).
     *   2. `successes == min(attemptCount, planCap)` (saturating counter).
     *   3. `quotaExceeded == attemptCount - successes`, each carrying
     *      `metric = 'analysis'` (faithful diagnostic).
     *
     * @dataProvider planCapProvider
     */
    public function testUsageRecordsNeverExceedCap(int $planCap, int $attemptCount): void
    {
        $scaffold = $this->bootScaffolding($planCap);
        $engine   = $this->makeEngine();

        $successes     = 0;
        $quotaExceeded = 0;

        for ($i = 0; $i < $attemptCount; $i++) {
            try {
                $engine->analyze('campaign', $scaffold['campaign']->id);
                $successes++;
            } catch (QuotaExceededException $e) {
                $quotaExceeded++;
                $this->assertSame(
                    'analysis',
                    $e->metric,
                    sprintf(
                        'Attempt %d/%d: QuotaExceededException must label the '
                        . 'analysis metric, got "%s".',
                        $i + 1,
                        $attemptCount,
                        $e->metric
                    )
                );
            }
        }

        // ── Invariant 1: P3 itself — conservation of quota ───────────
        // Count of analysis-metric Usage_Records inside the Subscription's
        // billing window MUST stay ≤ planCap, regardless of attemptCount.
        $usageCount = (int) UsageRecord::where('subscription_id', $scaffold['subscription']->id)
            ->where('metric', 'analysis')
            ->whereBetween('recorded_at', [
                $scaffold['subscription']->period_start,
                $scaffold['subscription']->period_end,
            ])
            ->count();

        $this->assertLessThanOrEqual(
            $planCap,
            $usageCount,
            sprintf(
                'P3 violated: %d analysis UsageRecord(s) exist in the billing '
                . 'window but the plan caps at %d. (attemptCount=%d)',
                $usageCount,
                $planCap,
                $attemptCount
            )
        );

        // ── Invariant 2: saturating counter ──────────────────────────
        // The gate is `count < max`, so the first `planCap` attempts MUST
        // each succeed and any further attempt MUST be rejected. Equivalent
        // formulation: `successes == min(attemptCount, planCap)`.
        $expectedSuccesses = min($attemptCount, $planCap);
        $this->assertSame(
            $expectedSuccesses,
            $successes,
            sprintf(
                'Expected exactly %d successful analyses (= min(%d, %d)) but '
                . 'observed %d. A drift here means PlanLimiter is either '
                . 'rejecting too early or too late.',
                $expectedSuccesses,
                $attemptCount,
                $planCap,
                $successes
            )
        );

        // ── Invariant 3: every surplus attempt rejected with the right metric ──
        $expectedRejections = $attemptCount - $expectedSuccesses;
        $this->assertSame(
            $expectedRejections,
            $quotaExceeded,
            sprintf(
                'Expected exactly %d QuotaExceededException(s) (= %d - %d) but '
                . 'observed %d.',
                $expectedRejections,
                $attemptCount,
                $expectedSuccesses,
                $quotaExceeded
            )
        );

        // ── Invariant 4 (corollary of 1 + 2): meter is append-only ───
        // Successes and persisted Usage_Records agree exactly. If the
        // engine ever billed twice or skipped a bill, this would catch it.
        $this->assertSame(
            $successes,
            $usageCount,
            sprintf(
                'UsageRecord count (%d) must equal successful invocation '
                . 'count (%d). A mismatch means the meter is double-billing '
                . 'or skipping billing on the success path.',
                $usageCount,
                $successes
            )
        );
    }

    // ────────────────────────────────────────────────────────────────
    //  Fixture helpers
    // ────────────────────────────────────────────────────────────────

    /**
     * Build a complete Workspace → MetaAccount → Campaign → 7 Insight
     * rows → Plan(planCap) → active Subscription → AiProvider chain,
     * sized so the engine reaches the quota gate inside `analyze()`.
     *
     * Notable choices:
     *   - `max_analyses_month = $planCap` parameterises the quota the
     *     property tests against. The Plan validator enforces `min:1`,
     *     so `$planCap` must be ≥ 1 (the provider guarantees this).
     *   - The Subscription's billing window straddles "now()" by 30 days
     *     on either side so every Usage_Record stamped during the test
     *     lands inside `[period_start, period_end]` and counts toward
     *     the cap — no spurious passes from out-of-window rows.
     *   - 7 Insight rows are seeded so the engine's history gate
     *     (`MIN_INSIGHT_DAYS = 7`) passes; otherwise the engine would
     *     reject with `RuntimeException` BEFORE reaching the quota gate
     *     and the property would degenerate.
     *   - The unique `$token` suffix on slug / login / email / meta_act_id
     *     keeps the fixtures non-clashing across data-provider iterations
     *     within the same test run.
     *
     * @return array{
     *   workspace:    Workspace,
     *   plan:         Plan,
     *   subscription: Subscription,
     *   campaign:     Campaign,
     *   provider:     AiProvider,
     * }
     */
    private function bootScaffolding(int $planCap): array
    {
        $token = str_replace('.', '-', uniqid('', true));

        $owner = BackendUser::create([
            'login'                 => 'quota_' . $token,
            'email'                 => 'quota_' . $token . '@example.com',
            'password'              => 'secret123',
            'password_confirmation' => 'secret123',
            'first_name'            => 'Quota',
            'last_name'             => 'Tester',
        ]);

        $workspace = Workspace::create([
            'name'     => 'Quota WS',
            'slug'     => 'quota-ws-' . $token,
            'owner_id' => $owner->id,
        ]);

        $plan = Plan::create([
            'code'               => 'quota-plan-' . $token,
            'name'               => 'Quota Plan',
            'monthly_price'      => 0,
            'max_meta_accounts'  => 1,
            'max_analyses_month' => $planCap,
            'auto_apply_allowed' => false,
        ]);

        $subscription = Subscription::create([
            'workspace_id' => $workspace->id,
            'plan_id'      => $plan->id,
            'status'       => 'active',
            'period_start' => now()->subDays(30)->toDateString(),
            'period_end'   => now()->addDays(30)->toDateString(),
        ]);

        $metaAccount = MetaAccount::create([
            'workspace_id' => $workspace->id,
            'meta_act_id'  => 'act_' . random_int(100000, 999999),
            'currency'     => 'USD',
            'access_token' => 'quota-token',
        ]);

        $campaign = Campaign::create([
            'meta_account_id' => $metaAccount->id,
            'meta_id'         => 'cmp_' . $token,
            'name'            => 'Quota Campaign',
            'status'          => 'ACTIVE',
            'daily_budget'    => 10.00,
        ]);

        // Seed 7 distinct Insight dates (today, today-1, …, today-6) so
        // the engine's `MIN_INSIGHT_DAYS = 7` history gate passes. The
        // exact KPIs are immaterial to P3 — only the row count matters.
        for ($d = 0; $d < 7; $d++) {
            Insight::create([
                'entity_type' => 'campaign',
                'entity_id'   => $campaign->id,
                'date'        => now()->subDays($d)->toDateString(),
                'impressions' => 1000,
                'clicks'      => 20,
                'spend'       => 5.0,
                'conversions' => 2,
            ]);
        }

        $provider = AiProvider::create([
            'workspace_id' => $workspace->id,
            'name'         => 'Quota Provider',
            'driver'       => 'openrouter',
            'model'        => 'anthropic/claude-3.5-sonnet',
            'api_key'      => 'sk-quota',
            'is_default'   => true,
        ]);

        return [
            'workspace'    => $workspace,
            'plan'         => $plan,
            'subscription' => $subscription,
            'campaign'     => $campaign,
            'provider'     => $provider,
        ];
    }

    /**
     * Build a fully-wired {@see RecommendationEngine} whose ONLY fake is
     * the LLM boundary.
     *
     * The stub {@see AiProviderInterface} returns a deterministic
     * {@see AiResponse} so the engine completes the full happy path —
     * persist Ai_Analysis, parse + validate recommendations,
     * `UsageMeter::record('analysis')` — without HTTP traffic. The
     * resolver is overridden to short-circuit DB lookups for the client
     * (the provider model still resolves via the engine's own
     * `lookupProviderModel()` query against the real seeded AiProvider).
     *
     * Every other collaborator runs as production code: this is what
     * makes the property faithful to the actual gate at runtime.
     */
    private function makeEngine(): RecommendationEngine
    {
        $stubClient = new class implements AiProviderInterface {
            public function complete(string $systemPrompt, string $userPrompt, array $options = []): AiResponse
            {
                return new AiResponse(
                    raw: '{"recommendations":[]}',
                    parsed: [
                        'recommendations' => [
                            [
                                'action_type' => 'pause',
                                'severity'    => 'low',
                                'rationale'   => 'Quota test stub recommendation.',
                                'payload'     => new \stdClass(),
                            ],
                        ],
                    ],
                    promptTokens: 100,
                    completionTokens: 50,
                    costUsd: 0.001,
                    model: 'stub'
                );
            }

            public function model(): string
            {
                return 'stub';
            }

            public function estimateCost(int $promptTokens, int $completionTokens): float
            {
                return 0.0;
            }
        };

        $resolver = new class($stubClient) extends AiProviderResolver {
            public function __construct(private readonly AiProviderInterface $stub) {}

            public function resolve(Workspace $workspace, ?int $forceProviderId = null): AiProviderInterface
            {
                return $this->stub;
            }
        };

        return new RecommendationEngine(
            $resolver,
            new PromptBuilder(),
            new ResponseParser(),
            new RecommendationValidator(),
            new MetricsAggregator(),
            new PlanLimiter(),
            new UsageMeter()
        );
    }
}
