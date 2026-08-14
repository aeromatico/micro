<?php declare(strict_types=1);

namespace Aero\MasterAds\Observers;

use Aero\MasterAds\Models\Subscription;
use Illuminate\Support\Facades\Log;

/**
 * SubscriptionObserver — reacts to Subscription persistence lifecycle.
 *
 * Hook `updated`: when a Subscription transitions to a new period (i.e. its
 * `period_start` or `period_end` changes), log a renewal marker. We do NOT
 * delete UsageRecords — `PlanLimiter::canRunAnalysis()` already filters by
 * the current period_start/period_end window, so consumption count resets
 * naturally per period (Requirement 9.6).
 *
 * Validates: Requirement 9.6
 */
class SubscriptionObserver
{
    public function updated(Subscription $sub): void
    {
        if (!$sub->wasChanged(['period_start', 'period_end'])) {
            return;
        }

        Log::info('[MasterAds][Subscription] period rolled over', [
            'subscription_id' => $sub->id,
            'workspace_id' => $sub->workspace_id,
            'plan_id' => $sub->plan_id,
            'new_period_start' => $sub->period_start?->toIso8601String(),
            'new_period_end' => $sub->period_end?->toIso8601String(),
            'old_period_start' => $sub->getOriginal('period_start'),
            'old_period_end' => $sub->getOriginal('period_end'),
        ]);

        // No UsageRecord cleanup — historical records remain for auditing.
        // PlanLimiter applies the current period window for quota calculations.
    }
}
