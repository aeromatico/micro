<?php declare(strict_types=1);

namespace Aero\MasterAds\Tests\Engine;

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
use Aero\MasterAds\Classes\Exceptions\AiProviderException;
use Aero\MasterAds\Classes\Exceptions\QuotaExceededException;
use Aero\MasterAds\Models\AiAnalysis;
use Aero\MasterAds\Models\AiProvider;
use Aero\MasterAds\Models\Campaign;
use Aero\MasterAds\Models\Insight;
use Aero\MasterAds\Models\MetaAccount;
use Aero\MasterAds\Models\Plan;
use Aero\MasterAds\Models\Recommendation;
use Aero\MasterAds\Models\Subscription;
use Aero\MasterAds\Models\UsageRecord;
use Aero\MasterAds\Models\Workspace;
use Backend\Models\User as BackendUser;
use Event;
use PluginTestCase;
use RuntimeException;

/**
 * RecommendationEngineTest — Integration-style coverage of the engine's
 * five terminal lifecycle paths.
 *
 * Extends {@see PluginTestCase} because the engine touches the database
 * heavily — Insight aggregation, AiAnalysis persistence, Recommendation
 * children, UsageRecord billing. Every scenario builds a full real
 * scaffolding chain (Workspace → MetaAccount → Campaign → 7+ Insight
 * rows → Plan → active Subscription → AiProvider) inside
 * {@see bootScaffolding()} and only mocks the boundary that hits the
 * outside world: the AI provider client.
 *
 * Strategy:
 *   - The {@see AiProviderResolver} is subclassed by an anonymous test
 *     class that returns a canned {@see AiProviderInterface} stub. No
 *     real HTTP, no OpenRouter, no provider SDK.
 *   - The stub provider's `complete()` either returns a deterministic
 *     {@see AiResponse} (success paths) or throws {@see AiProviderException}
 *     (failure path), exercising both halves of the engine's
 *     try/catch contract.
 *   - {@see PromptBuilder}, {@see ResponseParser},
 *     {@see RecommendationValidator}, {@see MetricsAggregator},
 *     {@see PlanLimiter} and {@see UsageMeter} run as the real
 *     production code — the only fake is the LLM boundary.
 *
 * Lifecycle paths exercised:
 *   1. Quota exhausted   → `QuotaExceededException`, no `AiAnalysis`.
 *   2. Insufficient days → `RuntimeException`, no `AiAnalysis`.
 *   3. No AI provider    → `AiProviderException`, no `AiAnalysis`.
 *   4. Happy path        → success `AiAnalysis`, 2 `Recommendation`s,
 *                          1 `UsageRecord`, event dispatched.
 *   5. LLM failure       → failed `AiAnalysis` with `error_message`,
 *                          NO recommendations, NO usage record,
 *                          exception rethrown.
 *
 * Validates: Requirements 6.2, 6.3, 6.9, 14.5.
 */
class RecommendationEngineTest extends PluginTestCase
{
    /**
     * Build a fresh Backend_User with unique login/email so the
     * Workspace owner FK rule (`exists:backend_users,id`) is satisfied.
     */
    private function createBackendUser(): BackendUser
    {
        $token = uniqid('', true);
        return BackendUser::create([
            'login'                 => 'engine_' . $token,
            'email'                 => 'engine_' . $token . '@example.com',
            'password'              => 'secret123',
            'password_confirmation' => 'secret123',
            'first_name'            => 'Engine',
            'last_name'             => 'Tester',
        ]);
    }

    /**
     * Build the full scaffolding chain the engine traverses end-to-end.
     *
     * Knobs:
     *   - `insightDays`   → number of distinct Insight dates ending today.
     *                       Set to 7 (the minimum) for happy paths, 3 to
     *                       exercise the insufficient-history rejection.
     *   - `withProvider`  → whether an `AiProvider` row exists.
     *                       Set to false to exercise the no-provider path.
     *   - `maxAnalyses`   → cap on `Plan.max_analyses_month` (validation
     *                       enforces ≥ 1, so quota exhaustion is staged
     *                       via a pre-inserted UsageRecord, not a 0 cap).
     *
     * @return array{
     *   workspace:    Workspace,
     *   plan:         Plan,
     *   subscription: Subscription,
     *   campaign:     Campaign,
     *   provider:     ?AiProvider,
     * }
     */
    private function bootScaffolding(
        int $insightDays = 7,
        bool $withProvider = true,
        int $maxAnalyses = 10
    ): array {
        $owner = $this->createBackendUser();

        $workspace = Workspace::create([
            'name'     => 'Engine WS',
            'slug'     => 'engine-ws-' . uniqid(),
            'owner_id' => $owner->id,
        ]);

        $plan = Plan::create([
            'code'               => 'engine-plan-' . uniqid(),
            'name'               => 'Engine Plan',
            'monthly_price'      => 0,
            'max_meta_accounts'  => 1,
            'max_analyses_month' => $maxAnalyses,
            'auto_apply_allowed' => false,
        ]);

        // Period spans a wide window so any UsageRecord written by the
        // engine in "now()" lands inside [period_start, period_end].
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
            'name'         => 'Engine Account',
            'currency'     => 'USD',
            'access_token' => 'engine-test-token',
        ]);

        $campaign = Campaign::create([
            'meta_account_id' => $metaAccount->id,
            'meta_id'         => 'cmp-' . uniqid(),
            'name'            => 'Engine Campaign',
            'objective'       => 'OUTCOME_SALES',
            'status'          => 'ACTIVE',
            'daily_budget'    => 50.00,
        ]);

        // Insight rows: one per day for `$insightDays` distinct dates
        // ending today. The lookback query (`lookback(7)`) covers the
        // last 7 days inclusively, so 7 rows satisfy the minimum bar.
        for ($d = 0; $d < $insightDays; $d++) {
            Insight::create([
                'entity_type' => 'campaign',
                'entity_id'   => $campaign->id,
                'date'        => now()->subDays($d)->toDateString(),
                'impressions' => 1000 + $d,
                'clicks'      => 20 + $d,
                'spend'       => 5.00,
                'conversions' => 2,
            ]);
        }

        $provider = null;
        if ($withProvider) {
            $provider = AiProvider::create([
                'workspace_id' => $workspace->id,
                'name'         => 'Engine Provider',
                'driver'       => 'openrouter',
                'model'        => 'anthropic/claude-3.5-sonnet',
                'api_key'      => 'sk-engine-test',
                'is_default'   => true,
            ]);
        }

        return [
            'workspace'    => $workspace,
            'plan'         => $plan,
            'subscription' => $subscription,
            'campaign'     => $campaign,
            'provider'     => $provider,
        ];
    }

    /**
     * Build an `AiProviderInterface` stub that returns the supplied
     * canned response (or throws the supplied exception) on `complete()`.
     *
     * @param  AiResponse|\Throwable $outcome  Canned response OR
     *                                         exception to throw.
     */
    private function makeStubClient($outcome): AiProviderInterface
    {
        return new class($outcome) implements AiProviderInterface {
            /** @param AiResponse|\Throwable $outcome */
            public function __construct(private readonly mixed $outcome) {}

            public function complete(string $systemPrompt, string $userPrompt, array $options = []): AiResponse
            {
                if ($this->outcome instanceof \Throwable) {
                    throw $this->outcome;
                }
                return $this->outcome;
            }

            public function model(): string
            {
                return 'test-stub-model';
            }

            public function estimateCost(int $promptTokens, int $completionTokens): float
            {
                return 0.0;
            }
        };
    }

    /**
     * Build an {@see AiProviderResolver} that bypasses DB lookup and
     * returns the supplied stub client. The engine still calls its own
     * `lookupProviderModel()` against the real AiProvider row scaffolded
     * in the test, so the persisted `ai_provider_id` FK stays valid.
     */
    private function makeStubResolver(AiProviderInterface $stubClient): AiProviderResolver
    {
        return new class($stubClient) extends AiProviderResolver {
            public function __construct(private readonly AiProviderInterface $stub) {}

            public function resolve(Workspace $workspace, ?int $forceProviderId = null): AiProviderInterface
            {
                return $this->stub;
            }
        };
    }

    /**
     * Build a {@see RecommendationEngine} wired with the supplied
     * resolver and the real production collaborators for everything
     * else. Defaults to the unmodified `AiProviderResolver` (so the
     * engine consults the real DB) when no override is supplied.
     */
    private function makeEngine(?AiProviderResolver $resolver = null): RecommendationEngine
    {
        return new RecommendationEngine(
            $resolver ?? new AiProviderResolver(),
            new PromptBuilder(),
            new ResponseParser(),
            new RecommendationValidator(),
            new MetricsAggregator(),
            new PlanLimiter(),
            new UsageMeter()
        );
    }

    /**
     * Build a canned successful {@see AiResponse} carrying two valid
     * recommendations that survive {@see RecommendationValidator}:
     *   - `adjust_budget` with a strictly positive daily_budget;
     *   - `pause` whose payload is intentionally empty (the validator
     *     accepts `{}` for pause/resume actions).
     */
    private function makeSuccessResponse(): AiResponse
    {
        return new AiResponse(
            raw: '{"recommendations":[...]}',
            parsed: [
                'summary'         => 'Two suggested actions.',
                'overall_health'  => 72,
                'recommendations' => [
                    [
                        'action_type'         => 'adjust_budget',
                        'severity'            => 'medium',
                        'rationale'           => 'CTR is below benchmark, lowering daily spend trims waste.',
                        'confidence'          => 80,
                        'expected_impact_pct' => 10,
                        'payload'             => ['daily_budget' => 35.00],
                    ],
                    [
                        'action_type'         => 'pause',
                        'severity'            => 'high',
                        'rationale'           => 'Conversion rate has collapsed in the last lookback window.',
                        'confidence'          => 90,
                        'expected_impact_pct' => 25,
                        'payload'             => new \stdClass(), // Schema-compatible "empty object".
                    ],
                ],
            ],
            promptTokens: 600,
            completionTokens: 400,
            costUsd: 0.0123,
            model: 'test-stub-model'
        );
    }

    // ==================================================================
    // 1. Quota exhausted
    // ==================================================================

    /**
     * When the active Subscription has consumed its monthly analysis
     * budget, {@see RecommendationEngine::analyze()} MUST raise
     * {@see QuotaExceededException} BEFORE any `AiAnalysis` row is
     * persisted (Requirement 6.3 — failed quota check must not pollute
     * audit history or bill the tenant).
     */
    public function testRejectsWhenQuotaExhausted(): void
    {
        // Plan caps at 1 analysis/month; pre-insert 1 UsageRecord to
        // exhaust it. (`max_analyses_month` validation enforces ≥ 1,
        // so we stage exhaustion via usage rather than a 0 cap.)
        $scaffold = $this->bootScaffolding(maxAnalyses: 1);

        UsageRecord::create([
            'subscription_id' => $scaffold['subscription']->id,
            'metric'          => 'analysis',
            'qty'             => 1,
            'recorded_at'     => now(),
        ]);

        $beforeAnalyses = AiAnalysis::withTrashed()->count();
        $beforeUsage    = UsageRecord::count();

        try {
            $this->makeEngine()->analyze('campaign', $scaffold['campaign']->id);
            $this->fail('Expected QuotaExceededException when the analysis budget is exhausted.');
        } catch (QuotaExceededException $e) {
            $this->assertSame('analysis', $e->metric, 'Exception must label the breached metric.');
        }

        $this->assertSame(
            $beforeAnalyses,
            AiAnalysis::withTrashed()->count(),
            'No AiAnalysis row may be created when quota is exhausted (Requirement 6.3).'
        );
        $this->assertSame(
            $beforeUsage,
            UsageRecord::count(),
            'No UsageRecord may be written on a quota-rejected attempt.'
        );
    }

    // ==================================================================
    // 2. Insufficient Insight history
    // ==================================================================

    /**
     * Targets with fewer than 7 distinct Insight dates MUST be rejected
     * with a {@see RuntimeException} naming "Insufficient Insight history"
     * (Requirement 6.2 — the LLM cannot reason without baseline data).
     * No `AiAnalysis` row is created.
     */
    public function testRejectsWhenInsufficientInsightHistory(): void
    {
        $scaffold = $this->bootScaffolding(insightDays: 3);

        $beforeAnalyses = AiAnalysis::withTrashed()->count();

        try {
            $this->makeEngine()->analyze('campaign', $scaffold['campaign']->id);
            $this->fail('Expected RuntimeException when the target has too few Insight days.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString(
                'Insufficient Insight history',
                $e->getMessage(),
                'Exception message must surface the "Insufficient Insight history" diagnostic.'
            );
        }

        $this->assertSame(
            $beforeAnalyses,
            AiAnalysis::withTrashed()->count(),
            'No AiAnalysis row may be created when Insight history is too thin (Requirement 6.2).'
        );
    }

    // ==================================================================
    // 3. No AI provider
    // ==================================================================

    /**
     * When the Workspace has no `AiProvider` configured, the real
     * resolver MUST raise {@see AiProviderException} and the engine MUST
     * propagate it without persisting any `AiAnalysis` row
     * (Requirement 6.9 + AiProviderResolver tier-5 fallback).
     */
    public function testRejectsWhenNoAiProvider(): void
    {
        $scaffold = $this->bootScaffolding(withProvider: false);

        $beforeAnalyses = AiAnalysis::withTrashed()->count();

        try {
            $this->makeEngine()->analyze('campaign', $scaffold['campaign']->id);
            $this->fail('Expected AiProviderException when no AI provider is configured.');
        } catch (AiProviderException $e) {
            $this->assertNotEmpty($e->getMessage(), 'AiProviderException must carry a diagnostic message.');
        }

        $this->assertSame(
            $beforeAnalyses,
            AiAnalysis::withTrashed()->count(),
            'No AiAnalysis row may be created when no provider is available.'
        );
    }

    // ==================================================================
    // 4. Happy path
    // ==================================================================

    /**
     * The end-to-end success path MUST:
     *   - persist exactly one `AiAnalysis` with `status = success`,
     *   - persist exactly two `Recommendation` rows with `status = pending`
     *     (matching the canned LLM response),
     *   - record exactly one `analysis` `UsageRecord` against the active
     *     Subscription, AND
     *   - dispatch the `aero.masterads.recommendation_generated` event.
     */
    public function testSuccessPathCreatesAnalysisAndRecommendations(): void
    {
        $scaffold = $this->bootScaffolding();

        $stubClient   = $this->makeStubClient($this->makeSuccessResponse());
        $stubResolver = $this->makeStubResolver($stubClient);

        Event::fake(['aero.masterads.recommendation_generated']);

        $analysis = $this->makeEngine($stubResolver)
            ->analyze('campaign', $scaffold['campaign']->id);

        // ---- AiAnalysis: exactly one, terminal-success ----
        $analyses = AiAnalysis::where('workspace_id', $scaffold['workspace']->id)->get();
        $this->assertCount(1, $analyses, 'Exactly one AiAnalysis must be persisted on the success path.');
        $this->assertSame('success', $analyses->first()->status);
        $this->assertNull(
            $analyses->first()->error_message,
            'error_message must be null on a terminal-success analysis.'
        );
        $this->assertSame($scaffold['provider']->id, (int) $analyses->first()->ai_provider_id);
        $this->assertGreaterThan(0, (int) $analyses->first()->tokens_used);

        // ---- Recommendations: two pending children ----
        $recs = Recommendation::where('ai_analysis_id', $analysis->id)->get();
        $this->assertCount(2, $recs, 'Two valid Recommendation rows must be persisted.');
        foreach ($recs as $rec) {
            $this->assertSame('pending', $rec->status, 'Every Recommendation must start as pending.');
        }

        // ---- UsageRecord: exactly one analysis-metric entry ----
        $usage = UsageRecord::where('subscription_id', $scaffold['subscription']->id)
            ->where('metric', 'analysis')
            ->get();
        $this->assertCount(
            1,
            $usage,
            'Exactly one UsageRecord(metric=analysis) must be billed on success.'
        );

        // ---- Event dispatched ----
        Event::assertDispatched('aero.masterads.recommendation_generated');
    }

    // ==================================================================
    // 5. AI call fails after Ai_Analysis is created
    // ==================================================================

    /**
     * When the AI provider throws {@see AiProviderException} during
     * `complete()`, the engine MUST:
     *   - mark the in-flight `AiAnalysis` as `status = failed` and
     *     persist `error_message`,
     *   - create NO `Recommendation` children (avoid orphans),
     *   - create NO `UsageRecord` (do not bill failed analyses —
     *     Requirement 6.9), AND
     *   - rethrow the original {@see AiProviderException} to the caller.
     */
    public function testFailedAiCallMarksAnalysisAsFailed(): void
    {
        $scaffold = $this->bootScaffolding();

        $aiError = new AiProviderException(
            'Upstream LLM 500',
            500,
            null,
            ['provider' => 'openrouter']
        );
        $stubClient   = $this->makeStubClient($aiError);
        $stubResolver = $this->makeStubResolver($stubClient);

        $rethrown = false;
        try {
            $this->makeEngine($stubResolver)
                ->analyze('campaign', $scaffold['campaign']->id);
        } catch (AiProviderException $e) {
            $rethrown = true;
            $this->assertSame('Upstream LLM 500', $e->getMessage(), 'The original exception must be rethrown intact.');
        }
        $this->assertTrue($rethrown, 'AiProviderException must propagate out of analyze() (Requirement 6.9).');

        // ---- AiAnalysis: exactly one, status=failed, with error_message ----
        $analyses = AiAnalysis::where('workspace_id', $scaffold['workspace']->id)->get();
        $this->assertCount(1, $analyses, 'A single AiAnalysis row must remain after the failed run.');
        $row = $analyses->first();
        $this->assertSame('failed', $row->status, 'Status must be transitioned to failed on AI error.');
        $this->assertNotEmpty($row->error_message, 'error_message must be populated on failure.');

        // ---- No orphan children ----
        $this->assertSame(
            0,
            Recommendation::where('ai_analysis_id', $row->id)->count(),
            'No Recommendation rows may be persisted when the AI call fails.'
        );

        // ---- Not billed ----
        $this->assertSame(
            0,
            UsageRecord::where('subscription_id', $scaffold['subscription']->id)->count(),
            'No UsageRecord may be written when the AI call fails (Requirement 6.9).'
        );
    }
}
