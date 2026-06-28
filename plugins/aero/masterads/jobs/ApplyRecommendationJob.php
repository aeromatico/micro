<?php declare(strict_types=1);

namespace Aero\MasterAds\Jobs;

use Aero\MasterAds\Classes\Engine\RecommendationApplierInterface;
use Aero\MasterAds\Classes\Exceptions\MetaApiException;
use Aero\MasterAds\Classes\Exceptions\UnsupportedActionTypeException;
use Aero\MasterAds\Models\Recommendation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * ApplyRecommendationJob
 *
 * Async wrapper around `RecommendationApplierInterface::apply()`. Dispatched
 * from the backend controller `onApplyNow` action and from the
 * `RecommendationObserver` when auto-apply is enabled at plan + workspace
 * level. Delegates the entire push-to-Meta + audit-trail flow to the
 * `RecommendationApplier` service so the controller responds immediately and
 * the heavy lifting happens on the Redis queue.
 *
 * Retries: `$tries = 1`. The applier is idempotent on
 * `(recommendation_id, success)` (Requirement 7.2, 7.12, 19.5), so a manual
 * retry is always safe; however leaning on Laravel's automatic retries would
 * fire duplicate Meta Graph API calls for transient transport failures
 * before the applier's idempotency check has a chance to short-circuit, so
 * we keep the count at 1 and surface the failure for a human-driven retry.
 *
 * Timeout: 120 seconds. Comfortably above the p99 Meta Graph API latency we
 * observe end-to-end (snapshot + mutate + snapshot), with margin for network
 * jitter.
 *
 * Logging carries a per-execution `correlation_id` (UUID) so the `started`,
 * `completed` and `failed` events of a single job invocation can be tied
 * together in log aggregators (Requirement 16.1).
 *
 * Validates: Requirements 7.1, 10.1, 10.2, 10.7, 16.1, 16.4, 19.5
 */
final class ApplyRecommendationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of attempts before the job is moved to `failed_jobs`. Kept at 1
     * intentionally; see class docblock.
     */
    public int $tries = 1;

    /**
     * Maximum execution time, in seconds. The worker will kill the process
     * if `handle()` runs longer than this.
     */
    public int $timeout = 120;

    /**
     * @param int $recommendationId  Primary key of the Recommendation to apply.
     *                               The model itself is intentionally NOT stored
     *                               on the job payload — we re-hydrate it inside
     *                               `handle()` to guarantee the latest state and
     *                               to keep the queue payload small.
     * @param int $appliedByUserId   Backend_User id authoring the application,
     *                               persisted on the resulting AppliedAction for
     *                               audit (Requirement 10.2).
     */
    public function __construct(
        public readonly int $recommendationId,
        public readonly int $appliedByUserId
    ) {
    }

    /**
     * Job entry point. Resolved at runtime by the container so we can inject
     * the `RecommendationApplierInterface` binding instead of newing up the
     * concrete service (lets tests swap a fake applier).
     *
     * @throws UnsupportedActionTypeException Re-thrown after logging; permanent
     *         error, no Meta call was made.
     * @throws MetaApiException Re-thrown after logging; the applier has already
     *         persisted a failed AppliedAction row for auditability.
     */
    public function handle(RecommendationApplierInterface $applier): void
    {
        $correlationId = (string) Str::uuid();
        $rec = Recommendation::findOrFail($this->recommendationId);

        Log::info('[MasterAds][ApplyRecommendation] started', [
            'recommendation_id' => $rec->id,
            'action_type' => $rec->action_type,
            'correlation_id' => $correlationId,
        ]);

        try {
            $action = $applier->apply($rec, $this->appliedByUserId);
            Log::info('[MasterAds][ApplyRecommendation] completed', [
                'recommendation_id' => $rec->id,
                'applied_action_id' => $action->id,
                'success' => $action->success,
                'correlation_id' => $correlationId,
            ]);
        } catch (UnsupportedActionTypeException $e) {
            // Permanent error — no retry. Surface it to `failed_jobs` so an
            // operator can investigate the offending Recommendation.
            Log::error('[MasterAds][ApplyRecommendation] unsupported action', [
                'recommendation_id' => $rec->id,
                'action_type' => $e->actionType,
                'correlation_id' => $correlationId,
            ]);
            throw $e;
        } catch (MetaApiException $e) {
            // The applier already persisted a failed AppliedAction; we only
            // need to log and re-throw so Laravel marks the job as failed.
            Log::error('[MasterAds][ApplyRecommendation] meta API failed', [
                'recommendation_id' => $rec->id,
                'error' => $e->getMessage(),
                'context' => $e->context,
                'correlation_id' => $correlationId,
            ]);
            throw $e;
        }
    }

    /**
     * Invoked by the queue worker after the last retry has failed. Emits a
     * terminal log entry so the failure is visible even when the audit row
     * could not be written (e.g. database unreachable).
     */
    public function failed(Throwable $e): void
    {
        Log::error('[MasterAds][ApplyRecommendation] failed', [
            'recommendation_id' => $this->recommendationId,
            'error' => $e->getMessage(),
        ]);
    }
}
