<?php declare(strict_types=1);

namespace Aero\MasterAds\Classes\Engine;

use Aero\MasterAds\Models\AiAnalysis;

/**
 * RecommendationEngineInterface — Contract that the Recommendation Engine
 * fulfils to orchestrate a full AI-driven analysis of a Meta entity.
 *
 * Implementations MUST honour the lifecycle described in `design.md`
 * (Algorithm 2):
 *
 *   1. Reject the call when the tenant has no remaining analysis quota
 *      (Requirement 6.3) — by throwing without creating an `Ai_Analysis`
 *      row, so the failure does not bill the workspace.
 *   2. Reject the call when the target has fewer than 7 distinct days of
 *      `Insight` history (Requirement 6.2) — same "no row, no usage"
 *      contract.
 *   3. Resolve the active `AiProvider` (Requirement 6.9): either the one
 *      explicitly passed via `options.force_provider`, or the workspace's
 *      `is_default` provider. A missing provider is a hard error.
 *   4. Persist an `Ai_Analysis` row in `status = running` and fold the
 *      look-back window into a `metrics_snapshot` via `MetricsAggregator`
 *      (Requirement 6.4).
 *   5. Build the system / user prompts (Requirement 6.5), call the provider,
 *      then parse + validate the response against `RECOMMENDATION_SCHEMA`
 *      (Requirements 6.6, 6.7).
 *   6. Persist each surviving recommendation in `status = pending`
 *      (Requirement 6.7), record one `Usage_Record` with metric `analysis`
 *      (Requirements 9.7, 14.5) and dispatch `aero.masterads.recommendation_generated`
 *      (Requirement 13.3 / 16.4).
 *   7. On provider failure: flip the `Ai_Analysis` to `status = failed`,
 *      persist `error_message`, do NOT record usage, do NOT create any
 *      child `Recommendation` rows (Requirement 6.9). The provider
 *      exception is re-thrown to the caller.
 *
 * The contract leaves transactional boundaries to the implementation. The
 * caller (typically `RunAiAnalysisJob` — Requirement 16.2) is expected to
 * surface the returned `AiAnalysis` to the controller / queue framework.
 *
 * Validates: Requirements 6.1, 6.2, 6.3, 6.4, 6.5, 6.6, 6.7, 6.8, 6.9, 6.10,
 *            14.5, 16.2, 16.4.
 */
interface RecommendationEngineInterface
{
    /**
     * Generate AI recommendations for a target (`campaign | adset | ad`).
     *
     * @param  string $targetType One of `campaign`, `adset`, `ad`.
     * @param  int    $targetId   Primary key of the target entity.
     * @param  array  $options    Engine knobs:
     *                            - `lookback_days` (int, default `14`):
     *                              window passed to `Insight::lookback()`
     *                              when folding metrics for the prompt
     *                              (Requirement 6.4).
     *                            - `force_provider` (int|null): override the
     *                              workspace's default `Ai_Provider` for
     *                              this run (Requirement 6.9 escape hatch).
     *                            - `triggered_by` (int|null): id of the
     *                              `Backend_User` that initiated the
     *                              analysis, persisted for audit.
     *
     * @return AiAnalysis The persisted analysis row. On success its `status`
     *                    is `success` and it owns N ≥ 0 `Recommendation`
     *                    children. On provider failure the status is
     *                    `failed`, `error_message` is populated, and the
     *                    underlying `AiProviderException` is re-thrown
     *                    after the row is saved.
     *
     * @throws \InvalidArgumentException                            When `$targetType` is not in {campaign,adset,ad}.
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When `$targetId` does not match a row of that type.
     * @throws \Aero\MasterAds\Classes\Exceptions\QuotaExceededException
     *         When the workspace has consumed its `max_analyses_month` cap.
     * @throws \RuntimeException                                    When the target has < 7 days of `Insight` history.
     * @throws \Aero\MasterAds\Classes\Exceptions\AiProviderException
     *         When no provider can be resolved or the upstream LLM call
     *         fails after retries (Requirement 6.9 — the `Ai_Analysis`
     *         is persisted with `status = failed` before the exception
     *         bubbles up).
     */
    public function analyze(string $targetType, int $targetId, array $options = []): AiAnalysis;
}
