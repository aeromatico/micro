<?php declare(strict_types=1);

namespace Aero\MasterAds\Jobs;

use Aero\MasterAds\Classes\Engine\RecommendationEngineInterface;
use Aero\MasterAds\Classes\Exceptions\AiProviderException;
use Aero\MasterAds\Classes\Exceptions\QuotaExceededException;
use Aero\MasterAds\Models\AiAnalysis;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * RunAiAnalysisJob — Async wrapper around `RecommendationEngineInterface::analyze()`.
 *
 * Dispatched by the backend controller when a user requests an AI-driven
 * analysis of a Meta target (`campaign | adset | ad`). The job offloads
 * the long-running LLM round-trip to the queue worker so the request
 * thread returns immediately (Requirement 16.1).
 *
 * Retries / timeout policy:
 *   - `tries = 1`: quota errors and AI provider errors are deterministic.
 *     Automatic retries would either re-bill the workspace (quota) or
 *     re-hit a known-broken upstream (provider). A manual "Retry" button
 *     from the backend is the preferred recovery path (Requirement 16.4).
 *   - `timeout = 180s`: hard ceiling that comfortably covers the engine's
 *     own per-step timeouts (metrics aggregation + prompt build + LLM
 *     call + persistence) (Requirement 10.7).
 *
 * Failure handling:
 *   - `QuotaExceededException` is *expected* — the engine throws it
 *     **before** persisting `Ai_Analysis`, so the workspace is not billed.
 *     We log at WARNING and let the job complete successfully so it does
 *     not show up as a failed job in the queue dashboard.
 *   - `AiProviderException` is logged at ERROR and re-thrown so the queue
 *     framework marks the job as failed. The engine has already persisted
 *     the `Ai_Analysis` row in `status = failed` with `error_message`
 *     populated, so no extra cleanup is required on this side.
 *   - Any other `Throwable` propagates to `failed()` which emits a
 *     terminal log line for operators (Requirement 16.4).
 *
 * Correlation:
 *   - A UUIDv4 `correlation_id` is generated per execution and attached
 *     to every log entry, letting operators thread the started → completed
 *     (or started → failed) lines together when grepping the queue logs.
 *
 * Validates: Requirements 6.1, 10.1, 10.2, 10.7, 16.1, 16.4
 */
final class RunAiAnalysisJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Number of automatic retries before the job is marked as failed.
     *
     * Set to 1 (no automatic retries) on purpose — see class-level docblock.
     */
    public int $tries = 1;

    /**
     * Hard timeout in seconds for a single attempt.
     */
    public int $timeout = 180;

    /**
     * @param  string $targetType One of `campaign`, `adset`, `ad`.
     * @param  int    $targetId   Primary key of the Meta target entity.
     * @param  array  $options    Forwarded to the engine (`lookback_days`,
     *                            `force_provider`, `triggered_by`, …).
     */
    public function __construct(
        public readonly string $targetType,
        public readonly int $targetId,
        public readonly array $options = []
    ) {
    }

    /**
     * Execute the analysis through the resolved {@see RecommendationEngineInterface}.
     */
    public function handle(RecommendationEngineInterface $engine): void
    {
        $correlationId = (string) Str::uuid();

        Log::info('[MasterAds][RunAiAnalysis] started', [
            'target_type'    => $this->targetType,
            'target_id'      => $this->targetId,
            'correlation_id' => $correlationId,
        ]);

        try {
            /** @var AiAnalysis $analysis */
            $analysis = $engine->analyze($this->targetType, $this->targetId, $this->options);

            Log::info('[MasterAds][RunAiAnalysis] completed', [
                'analysis_id'           => $analysis->id,
                'status'                => $analysis->status,
                'tokens_used'           => $analysis->tokens_used,
                'cost_usd'              => $analysis->cost_usd,
                'recommendations_count' => $analysis->recommendations()->count(),
                'correlation_id'        => $correlationId,
            ]);
        } catch (QuotaExceededException $e) {
            // Expected: log and let the job complete (no retry).
            // The engine threw before persisting Ai_Analysis, so the
            // workspace was not billed (Requirement 6.3).
            Log::warning('[MasterAds][RunAiAnalysis] quota exceeded', [
                'target_type'    => $this->targetType,
                'target_id'      => $this->targetId,
                'metric'         => $e->metric,
                'correlation_id' => $correlationId,
            ]);
        } catch (AiProviderException $e) {
            // Engine has already persisted Ai_Analysis with status=failed
            // and error_message populated (Requirement 6.9). Log and let
            // the job fail so the worker surfaces it in the dashboard.
            Log::error('[MasterAds][RunAiAnalysis] AI provider failed', [
                'target_type'    => $this->targetType,
                'target_id'      => $this->targetId,
                'error'          => $e->getMessage(),
                'correlation_id' => $correlationId,
            ]);
            throw $e;
        }
    }

    /**
     * Called by the queue framework after `tries` attempts have been
     * exhausted (i.e. after the single attempt fails). Emits a terminal
     * log line for operators.
     */
    public function failed(Throwable $e): void
    {
        Log::error('[MasterAds][RunAiAnalysis] failed', [
            'target_type' => $this->targetType,
            'target_id'   => $this->targetId,
            'error'       => $e->getMessage(),
        ]);
    }
}
