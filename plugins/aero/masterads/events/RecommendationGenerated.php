<?php declare(strict_types=1);

namespace Aero\MasterAds\Events;

/**
 * RecommendationGenerated
 *
 * Domain event dispatched when an `Ai_Analysis` finishes successfully
 * (`status = success`) and the Recommendation_Engine has materialised the
 * resulting Recommendation rows.
 *
 * Fired by: `Aero\MasterAds\Classes\Ai\RecommendationEngine` (invoked by
 * `GenerateRecommendationsJob`) right after persisting the Ai_Analysis and
 * its child Recommendations.
 *
 * Payload:
 *   - `aiAnalysis`: the Ai_Analysis row that produced the recommendations.
 *     Listeners may eager-load `recommendations` to iterate over them
 *     (e.g. `NotifyRecommendationListener`, Requirement 13.5).
 *
 * Note: existing code paths dispatch this milestone using the legacy string
 * event name `aero.masterads.recommendation_generated`. This class exists
 * for type-safe future use; `Plugin::boot()` (task 15.5) registers both the
 * string-based and class-based listeners.
 *
 * Validates: Requirements 13.3, 13.5
 */
final class RecommendationGenerated
{
    public function __construct(
        public readonly \Aero\MasterAds\Models\AiAnalysis $aiAnalysis,
    ) {
    }
}
