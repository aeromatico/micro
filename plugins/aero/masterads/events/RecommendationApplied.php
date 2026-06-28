<?php declare(strict_types=1);

namespace Aero\MasterAds\Events;

/**
 * RecommendationApplied
 *
 * Domain event dispatched when a Recommendation is applied successfully
 * against the Graph_API and the resulting Applied_Action row has been
 * persisted (audit trail for Requirement 8).
 *
 * Fired by: `Aero\MasterAds\Classes\Ai\RecommendationApplier` (invoked by
 * `ApplyRecommendationJob`) after the Meta mutation succeeds and the
 * Applied_Action row is committed within the same transaction.
 *
 * Payload:
 *   - `recommendation`: the Recommendation that was just applied. Its
 *     `status` should already be `applied` at the time the event fires.
 *   - `appliedAction`: the Applied_Action audit row produced by the apply
 *     (carries `meta_response`, `applied_at` and the actor reference).
 *
 * Note: existing code paths dispatch this milestone using the legacy string
 * event name `aero.masterads.recommendation_applied`. This class exists for
 * type-safe future use; `Plugin::boot()` (task 15.5) registers both the
 * string-based and class-based listeners.
 *
 * Validates: Requirements 13.4
 */
final class RecommendationApplied
{
    public function __construct(
        public readonly \Aero\MasterAds\Models\Recommendation $recommendation,
        public readonly \Aero\MasterAds\Models\AppliedAction $appliedAction,
    ) {
    }
}
