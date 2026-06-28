<?php declare(strict_types=1);

namespace Aero\MasterAds\Observers;

use Aero\MasterAds\Classes\Billing\PlanLimiter;
use Aero\MasterAds\Jobs\ApplyRecommendationJob;
use Aero\MasterAds\Models\Recommendation;
use Illuminate\Support\Facades\Log;

/**
 * RecommendationObserver — reacts to Recommendation persistence lifecycle.
 *
 * Hook `created`: if the parent workspace's Plan has `auto_apply_allowed=true`
 * AND the workspace.settings.auto_apply_threshold is met by the recommendation's
 * severity/confidence, dispatch `ApplyRecommendationJob` automatically.
 *
 * Hook `created`: if `auto_apply_allowed=false`, NO-OP (manual review path).
 *
 * Registered in `Plugin::boot()` (task 15.5) via
 * `Recommendation::observe(RecommendationObserver::class)`. The observer is
 * resolved through the service container so `PlanLimiter` is auto-injected.
 *
 * Validates: Requirements 9.8, 9.9
 */
class RecommendationObserver
{
    public function __construct(private readonly PlanLimiter $limiter) {}

    /**
     * Eloquent `created` hook — fires once per freshly persisted Recommendation.
     *
     * Decision tree:
     *   1. Resolve the parent AiAnalysis → Workspace; bail out if either is
     *      missing (defensive: should never happen given FK constraints).
     *   2. Ask `PlanLimiter::canAutoApply()` whether the active plan permits
     *      auto-apply at all. When `false` (no active subscription OR
     *      `auto_apply_allowed=false` per Requirement 9.9), do nothing —
     *      the recommendation stays `pending` and waits for manual review.
     *   3. Read the workspace-level severity allowlist from
     *      `workspace.settings.auto_apply_max_severity`. Default to `['low']`
     *      so a workspace that has not opted in only ever auto-applies the
     *      safest changes.
     *   4. If the recommendation's severity falls inside the allowlist, flip
     *      its status to `approved` (via `saveQuietly()` to avoid re-firing
     *      this observer) and dispatch `ApplyRecommendationJob` with the
     *      workspace owner as the `applied_by` user for audit.
     */
    public function created(Recommendation $rec): void
    {
        $analysis = $rec->ai_analysis;
        if (!$analysis) {
            return;
        }
        $workspace = $analysis->workspace;
        if (!$workspace) {
            return;
        }

        if (!$this->limiter->canAutoApply($workspace)) {
            // Plan does not allow auto-apply OR no active subscription.
            return;
        }

        // Workspace-level threshold: by default, auto-apply only LOW severity recs
        // unless settings.auto_apply_max_severity overrides.
        $allowedSeverities = $workspace->settings['auto_apply_max_severity']
            ?? ['low']; // conservative default
        if (!is_array($allowedSeverities)) {
            $allowedSeverities = [$allowedSeverities];
        }

        if (!in_array($rec->severity, $allowedSeverities, true)) {
            return; // severity outside auto-apply policy
        }

        // Auto-approve and dispatch.
        $rec->status = 'approved';
        $rec->saveQuietly(); // avoid recursive observer trigger

        // Use workspace owner as "applied_by" for audit.
        $userId = $workspace->owner_id;
        ApplyRecommendationJob::dispatch($rec->id, $userId);

        Log::info('[MasterAds][AutoApply] dispatched for recommendation', [
            'recommendation_id' => $rec->id,
            'workspace_id' => $workspace->id,
            'severity' => $rec->severity,
        ]);
    }
}
