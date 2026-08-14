<?php declare(strict_types=1);

namespace Aero\MasterAds\Classes\Engine;

use Aero\MasterAds\Classes\Ai\AiProviderResolver;
use Aero\MasterAds\Classes\Ai\PromptBuilder;
use Aero\MasterAds\Classes\Ai\RecommendationValidator;
use Aero\MasterAds\Classes\Ai\ResponseParser;
use Aero\MasterAds\Classes\Billing\PlanLimiter;
use Aero\MasterAds\Classes\Billing\UsageMeter;
use Aero\MasterAds\Classes\Exceptions\AiProviderException;
use Aero\MasterAds\Classes\Exceptions\QuotaExceededException;
use Aero\MasterAds\Models\Ad;
use Aero\MasterAds\Models\AdSet;
use Aero\MasterAds\Models\AiAnalysis;
use Aero\MasterAds\Models\AiProvider;
use Aero\MasterAds\Models\Campaign;
use Aero\MasterAds\Models\Insight;
use Aero\MasterAds\Models\Recommendation;
use Aero\MasterAds\Models\Workspace;
use Event;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * RecommendationEngine — Orchestrates a full AI analysis run from quota
 * gate to persisted `Recommendation` children, matching Algorithm 2 of
 * `design.md`.
 *
 * The engine is deliberately stateless: every collaborator is injected
 * through the constructor and the only mutation it owns is the side-effects
 * documented per step in `analyze()` (DB writes, event dispatch). That keeps
 * the unit tests in 9.4 simple — mock the seven dependencies, assert the
 * lifecycle.
 *
 * Transaction strategy (Requirement 6.9 + concurrency safety):
 *
 *  - The provider call (`AiProviderInterface::complete`) runs OUTSIDE any
 *    DB transaction so a long HTTP round-trip never holds an InnoDB write
 *    lock on `aero_masterads_ai_analyses`.
 *  - When the LLM throws `AiProviderException` we update the existing
 *    `Ai_Analysis` row to `status = failed`, persist `error_message`, then
 *    re-throw. NO `Usage_Record` is created, NO child rows are inserted.
 *  - The post-LLM persistence (raw_response, tokens, cost, recommendations,
 *    final status, usage record) IS wrapped in `DB::transaction(...)` so
 *    callers cannot observe a partially-applied "success" state.
 *
 * Validates: Requirements 6.1, 6.2, 6.3, 6.4, 6.5, 6.6, 6.7, 6.8, 6.9, 6.10,
 *            14.5, 16.2, 16.4.
 */
final class RecommendationEngine implements RecommendationEngineInterface
{
    /**
     * Default look-back window when the caller does not override
     * `options.lookback_days`. The same default is enforced at the prompt
     * layer (`PromptBuilder::user()`), keeping both sides aligned.
     */
    private const DEFAULT_LOOKBACK_DAYS = 14;

    /**
     * Minimum number of distinct `Insight.date` values the target must have
     * before an analysis is allowed to run (Requirement 6.2).
     */
    private const MIN_INSIGHT_DAYS = 7;

    public function __construct(
        private readonly AiProviderResolver $resolver,
        private readonly PromptBuilder $prompt,
        private readonly ResponseParser $parser,
        private readonly RecommendationValidator $validator,
        private readonly MetricsAggregator $aggregator,
        private readonly PlanLimiter $limiter,
        private readonly UsageMeter $meter
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function analyze(string $targetType, int $targetId, array $options = []): AiAnalysis
    {
        // ---- 1. Resolve target model class & row -------------------------
        $target = $this->loadTarget($targetType, $targetId);

        // ---- 2. Walk relations up to the owning Workspace ---------------
        $workspace = $this->resolveWorkspace($targetType, $target);

        // ---- 3. Quota gate (Requirement 6.3) ----------------------------
        // No Ai_Analysis row is created when quota is exhausted: the failure
        // must NOT bill the tenant or pollute audit history.
        if (!$this->limiter->canRunAnalysis($workspace)) {
            throw new QuotaExceededException(
                'Analysis quota exhausted',
                0,
                null,
                ['workspace_id' => $workspace->id],
                'analysis'
            );
        }

        // ---- 4. Insight history gate (Requirement 6.2) ------------------
        // The LLM cannot reason about trends without a baseline; reject
        // early so we don't burn tokens on a target whose data is too thin.
        $insightDays = (int) Insight::query()
            ->forEntity($targetType, $targetId)
            ->lookback(self::MIN_INSIGHT_DAYS)
            ->distinct()
            ->count('date');

        if ($insightDays < self::MIN_INSIGHT_DAYS) {
            throw new RuntimeException(sprintf(
                'Insufficient Insight history for %s %d: need >=%d days, have %d',
                $targetType,
                $targetId,
                self::MIN_INSIGHT_DAYS,
                $insightDays
            ));
        }

        // ---- 5. Provider resolution (Requirement 6.9) -------------------
        // resolver->resolve() throws AiProviderException when no provider
        // is available — that path is the caller's responsibility to handle.
        $forceProvider = isset($options['force_provider']) ? (int) $options['force_provider'] : null;
        $client = $this->resolver->resolve($workspace, $forceProvider);

        // The resolver returns the *client* (AiProviderInterface). We still
        // need the AiProvider model to populate `Ai_Analysis.ai_provider_id`.
        // Replicate the resolver's primary election here so the FK matches
        // the same row the resolver picked.
        $providerModel = $this->lookupProviderModel($workspace, $forceProvider);
        if ($providerModel === null) {
            // Defence-in-depth: resolver said yes but our inline lookup
            // missed (shouldn't happen given identical query logic). Fail
            // loud so we never persist an Ai_Analysis with NULL FK.
            throw new AiProviderException(
                'AI provider resolved but model lookup failed',
                0,
                null,
                ['workspace_id' => $workspace->id, 'force_provider' => $forceProvider]
            );
        }

        // ---- 6. Persist Ai_Analysis in `running` state ------------------
        $aiAnalysis = new AiAnalysis();
        $aiAnalysis->workspace_id = $workspace->id;
        $aiAnalysis->ai_provider_id = $providerModel->id;
        $aiAnalysis->target_type = $targetType;
        $aiAnalysis->target_id = $targetId;
        $aiAnalysis->status = 'running';
        $aiAnalysis->tokens_used = 0;
        $aiAnalysis->cost_usd = 0;
        $aiAnalysis->save();

        // ---- 7. Build the metrics snapshot (Requirement 6.4) ------------
        $lookback = (int) ($options['lookback_days'] ?? self::DEFAULT_LOOKBACK_DAYS);
        if ($lookback <= 0) {
            $lookback = self::DEFAULT_LOOKBACK_DAYS;
        }

        $snapshot = $this->aggregator->initialSnapshot();
        Insight::query()
            ->forEntity($targetType, $targetId)
            ->lookback($lookback)
            ->orderBy('date')
            ->each(function (Insight $i) use (&$snapshot) {
                $this->aggregator->fold($snapshot, $i);
            });
        $derived = $this->aggregator->finalize($snapshot);
        $snapshot['lookback_days'] = $lookback;

        // ---- 8. Build prompts (Requirement 6.5) -------------------------
        $system = $this->prompt->system($targetType);
        $user = $this->prompt->user($target, $snapshot, $derived, $options);

        // Persist snapshot + prompts for reproducibility (Requirement 6.10).
        $aiAnalysis->metrics_snapshot = array_merge($snapshot, ['derived' => $derived]);
        $aiAnalysis->prompt_payload = ['system' => $system, 'user' => $user];
        $aiAnalysis->save();

        // ---- 9. LLM call (outside of any DB transaction) ----------------
        // On AiProviderException: mark failed + persist error_message + rethrow.
        // NO Usage_Record is written, NO Recommendation rows are inserted
        // (Requirement 6.9 — the failed analysis must NOT bill the tenant).
        try {
            $response = $client->complete($system, $user, [
                'temperature' => 0.2,
                'max_tokens'  => 4000,
                'json_schema' => PromptBuilder::RECOMMENDATION_SCHEMA,
            ]);
        } catch (AiProviderException $e) {
            $aiAnalysis->status = 'failed';
            $aiAnalysis->error_message = $e->getMessage();
            $aiAnalysis->save();
            throw $e;
        }

        // ---- 10..12. Persistence transaction ----------------------------
        // Wrap the success path in a single transaction so callers never
        // observe a half-persisted analysis (e.g. Recommendation rows
        // without a final-status parent).
        DB::transaction(function () use ($aiAnalysis, $response, $target): void {
            // Persist raw LLM artefacts (Requirement 6.10, 16.2).
            $aiAnalysis->raw_response = [
                'raw'    => $response->raw,
                'parsed' => $response->parsed,
                'model'  => $response->model,
            ];
            $aiAnalysis->tokens_used = $response->promptTokens + $response->completionTokens;
            $aiAnalysis->cost_usd = $response->costUsd;
            $aiAnalysis->save();

            // Parse + validate + persist children (Requirements 6.6, 6.7).
            $items = $this->parser->parse($response, PromptBuilder::RECOMMENDATION_SCHEMA);

            foreach ($items as $item) {
                if (!$this->validator->validate($item, $target)) {
                    // Invalid item → skip silently. Per Requirement 6.6 we
                    // discard rather than abort: a single bad recommendation
                    // must not poison the whole analysis.
                    continue;
                }

                $rec = new Recommendation();
                $rec->ai_analysis_id = $aiAnalysis->id;
                $rec->action_type = (string) $item['action_type'];
                $rec->severity = (string) $item['severity'];
                $rec->status = 'pending';
                $rec->rationale = (string) $item['rationale'];
                $rec->payload = is_array($item['payload']) ? $item['payload'] : [];

                // Compose `expected_impact` from optional `confidence` and
                // `expected_impact_pct` fields (Requirement 6.7 — keep the
                // forecast attached to the row for the review UI).
                $expected = [];
                if (array_key_exists('confidence', $item) && is_numeric($item['confidence'])) {
                    $expected['confidence'] = (float) $item['confidence'];
                }
                if (array_key_exists('expected_impact_pct', $item) && is_numeric($item['expected_impact_pct'])) {
                    $expected['expected_impact_pct'] = (float) $item['expected_impact_pct'];
                }
                if ($expected !== []) {
                    $rec->expected_impact = $expected;
                }

                $rec->save();
            }

            // Mark analysis terminal-success (Requirement 6.8).
            $aiAnalysis->status = 'success';
            $aiAnalysis->error_message = null;
            $aiAnalysis->save();

            // Record usage AFTER the analysis is final-success so a rollback
            // here also undoes the meter increment (Requirements 9.7, 14.5).
            $subscription = $this->limiter->activeSubscription($aiAnalysis->workspace);
            if ($subscription !== null) {
                $this->meter->record($subscription, 'analysis', 1);
            }
        });

        // ---- 13. Event dispatch (Requirement 16.4) ----------------------
        // Fired AFTER commit so listeners always see the persisted state.
        Event::dispatch('aero.masterads.recommendation_generated', [$aiAnalysis]);

        // ---- 14. Return -------------------------------------------------
        return $aiAnalysis->fresh(['recommendations']);
    }

    /**
     * Resolve the target Eloquent model for a (`$targetType`, `$targetId`)
     * pair, throwing `InvalidArgumentException` for unknown types and the
     * Eloquent `ModelNotFoundException` when the id is missing.
     *
     * @return Campaign|AdSet|Ad
     */
    private function loadTarget(string $targetType, int $targetId)
    {
        $modelClass = match ($targetType) {
            'campaign' => Campaign::class,
            'adset'    => AdSet::class,
            'ad'       => Ad::class,
            default    => throw new InvalidArgumentException(
                "Invalid target_type '{$targetType}': expected one of campaign, adset, ad"
            ),
        };

        /** @var Campaign|AdSet|Ad $target */
        $target = $modelClass::findOrFail($targetId);
        return $target;
    }

    /**
     * Walk the relation chain from any Meta entity up to its owning
     * `Workspace`.
     *
     *   - Campaign → meta_account → workspace
     *   - AdSet    → campaign → meta_account → workspace
     *   - Ad       → ad_set → campaign → meta_account → workspace
     *
     * Throws `RuntimeException` if any link in the chain is broken — that
     * would indicate database corruption (FK enforcement should normally
     * prevent it) and is unsafe to silently swallow because the analysis
     * could otherwise land in an unowned Workspace.
     */
    private function resolveWorkspace(string $targetType, $target): Workspace
    {
        $metaAccount = match ($targetType) {
            'campaign' => $target->meta_account,
            'adset'    => $target->campaign?->meta_account,
            'ad'       => $target->ad_set?->campaign?->meta_account,
            default    => null,
        };

        $workspace = $metaAccount?->workspace;

        if (!$workspace instanceof Workspace) {
            throw new RuntimeException(sprintf(
                'Cannot resolve owning Workspace for %s %d: relation chain broken',
                $targetType,
                $target->id ?? 0
            ));
        }

        return $workspace;
    }

    /**
     * Mirror of the primary path of `AiProviderResolver::resolve()` to
     * obtain the actual `AiProvider` row id we need for the FK.
     *
     * Kept intentionally narrow (force_provider, then workspace's default)
     * because that's the contract documented for this task; if the resolver
     * silently falls back to a non-default or global provider, we re-issue
     * a broader lookup to find any match the resolver could have picked.
     *
     * Uses `withoutGlobalScope('tenant')` so the FK lookup is deterministic
     * across HTTP / queue / console contexts.
     */
    private function lookupProviderModel(Workspace $workspace, ?int $forceProviderId): ?AiProvider
    {
        if ($forceProviderId !== null) {
            return AiProvider::withoutGlobalScope('tenant')->find($forceProviderId);
        }

        $provider = AiProvider::withoutGlobalScope('tenant')
            ->where('workspace_id', $workspace->id)
            ->where('is_default', true)
            ->first();

        if ($provider !== null) {
            return $provider;
        }

        // Resolver's fallback tiers: any workspace provider, then global
        // default. Mirror them so the persisted FK matches what the client
        // is actually talking to.
        $provider = AiProvider::withoutGlobalScope('tenant')
            ->where('workspace_id', $workspace->id)
            ->orderBy('id')
            ->first();

        if ($provider !== null) {
            return $provider;
        }

        return AiProvider::withoutGlobalScope('tenant')
            ->whereNull('workspace_id')
            ->where('is_default', true)
            ->first();
    }
}
