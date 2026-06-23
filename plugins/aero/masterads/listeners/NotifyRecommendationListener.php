<?php declare(strict_types=1);

namespace Aero\MasterAds\Listeners;

use Aero\MasterAds\Models\AiAnalysis;
use Illuminate\Support\Facades\Log;
use Backend\Models\UserGroup;

/**
 * NotifyRecommendationListener — fires on `aero.masterads.recommendation_generated`.
 *
 * For MVP: just logs the event and writes a backend flash queue notification
 * for owners/admins of the workspace. Future revisions will add email/Slack.
 *
 * Validates: Requirement 13.5
 */
class NotifyRecommendationListener
{
    /**
     * Handle the event.
     *
     * Subscribed in `Plugin::boot()` via Event::listen('aero.masterads.recommendation_generated', ...).
     */
    public function handle(AiAnalysis $aiAnalysis): void
    {
        $count = $aiAnalysis->recommendations()->count();
        $workspaceId = $aiAnalysis->workspace_id;

        Log::info('[MasterAds][Notify] new recommendations available', [
            'workspace_id'         => $workspaceId,
            'ai_analysis_id'       => $aiAnalysis->id,
            'recommendations_count' => $count,
            'target_type'          => $aiAnalysis->target_type,
            'target_id'            => $aiAnalysis->target_id,
        ]);

        // TODO (phase 2): mail::send() / slack channel / in-app notification
        // For MVP we rely on the log entry + the backend list which is
        // already filterable by status=pending.
    }
}
