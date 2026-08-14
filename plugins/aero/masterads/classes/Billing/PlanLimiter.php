<?php declare(strict_types=1);

namespace Aero\MasterAds\Classes\Billing;

use Aero\MasterAds\Models\MetaAccount;
use Aero\MasterAds\Models\Subscription;
use Aero\MasterAds\Models\UsageRecord;
use Aero\MasterAds\Models\Workspace;

/**
 * PlanLimiter — Pure query service that enforces plan-based quotas.
 *
 * No side effects: every method is a read-only function over the database.
 * Same inputs at the same database state always yield the same result
 * (deterministic & idempotent), which is what makes it safe to call from
 * controllers, jobs, console commands, and property-based tests alike.
 *
 * Data sources:
 *   - `Workspace.subscriptions` filtered to `status IN ('active','trialing')`
 *     (the "active" Subscription is the most recently ending one — i.e. the
 *     one whose `period_end` is the latest).
 *   - `Subscription.plan` for the quota caps:
 *       - `max_analyses_month`  → monthly analysis budget
 *       - `max_meta_accounts`   → cap on connected Meta accounts
 *       - `auto_apply_allowed`  → boolean flag gating auto-apply
 *   - `UsageRecord` rows scoped to the active Subscription's billing window
 *     (`recorded_at` BETWEEN `period_start` AND `period_end`) and metered
 *     under the `'analysis'` metric.
 *
 * Failure modes:
 *   - No active Subscription → every "can*" check returns `false`; usage
 *     accounting returns 0. The caller is responsible for surfacing a
 *     user-friendly message such as "Plan no contratado".
 *
 * Validates: Requirements 6.3, 9.3, 9.4, 9.5, 9.8, 9.9.
 */
final class PlanLimiter
{
    /**
     * Decide whether the Workspace may run a new AI analysis right now.
     *
     * A new analysis is allowed iff:
     *   1. The Workspace has an active (status `active` or `trialing`)
     *      Subscription, AND
     *   2. The number of `analysis` `UsageRecord` rows recorded within the
     *      current billing window is strictly less than the Plan's
     *      `max_analyses_month` cap.
     *
     * Validates: Requirements 6.3, 9.3, 9.5.
     *
     * @param  Workspace $ws The tenant whose quota is being checked.
     * @return bool          True when the analysis may proceed.
     */
    public function canRunAnalysis(Workspace $ws): bool
    {
        $sub = $this->activeSubscription($ws);
        if ($sub === null) {
            return false;
        }

        $max = (int) $sub->plan->max_analyses_month;
        $count = $this->countAnalysesInPeriod($sub);

        return $count < $max;
    }

    /**
     * Decide whether the Workspace may connect another Meta_Account.
     *
     * The cap is enforced against the *total* number of MetaAccount rows
     * already tied to this Workspace — independent of token state or any
     * soft-disabled flag, because the plan limit is about "connected
     * accounts", not "currently working accounts".
     *
     * Returns `false` when no active Subscription exists (Workspace without
     * a contracted plan cannot grow its surface area).
     *
     * Validates: Requirements 9.4, 9.8.
     *
     * @param  Workspace $ws The tenant whose quota is being checked.
     * @return bool          True when a new Meta_Account may be connected.
     */
    public function canConnectMetaAccount(Workspace $ws): bool
    {
        $sub = $this->activeSubscription($ws);
        if ($sub === null) {
            return false;
        }

        $current = MetaAccount::where('workspace_id', $ws->id)->count();

        return $current < (int) $sub->plan->max_meta_accounts;
    }

    /**
     * Decide whether the Workspace may auto-apply recommendations.
     *
     * Auto-apply is gated by a per-Plan boolean: lower tiers ship with
     * `auto_apply_allowed = false` (the user must approve every change),
     * higher tiers flip the bit to true. A Workspace without an active
     * Subscription is never allowed to auto-apply.
     *
     * Validates: Requirement 9.9.
     *
     * @param  Workspace $ws The tenant whose flag is being checked.
     * @return bool          True when auto-apply is permitted by the plan.
     */
    public function canAutoApply(Workspace $ws): bool
    {
        $sub = $this->activeSubscription($ws);

        return $sub !== null && (bool) $sub->plan->auto_apply_allowed;
    }

    /**
     * Resolve the Workspace's currently active Subscription, if any.
     *
     * "Active" here means `status IN ('active','trialing')`. When more than
     * one such row exists (e.g. an overlapping renewal that has not yet
     * superseded the prior period), the row with the latest `period_end` is
     * preferred — it represents the most-recently extended billing window.
     *
     * Returns `null` when the Workspace has no active or trialing
     * Subscription at all.
     *
     * Validates: Requirements 9.3, 9.4, 9.5, 9.8, 9.9.
     *
     * @param  Workspace          $ws The tenant whose subscription is requested.
     * @return Subscription|null      Active Subscription or null when absent.
     */
    public function activeSubscription(Workspace $ws): ?Subscription
    {
        /** @var Subscription|null $sub */
        $sub = $ws->subscriptions()
            ->whereIn('status', ['active', 'trialing'])
            ->orderByDesc('period_end')
            ->first();

        return $sub;
    }

    /**
     * Number of analyses the Workspace may still run in the current period.
     *
     * Equals `max(0, plan.max_analyses_month - count(UsageRecord in period))`.
     * Returns 0 when there is no active Subscription (no plan → no budget).
     *
     * Validates: Requirements 6.3, 9.3, 9.5.
     *
     * @param  Workspace $ws The tenant whose remaining budget is requested.
     * @return int           Non-negative count of remaining analyses.
     */
    public function remainingAnalyses(Workspace $ws): int
    {
        $sub = $this->activeSubscription($ws);
        if ($sub === null) {
            return 0;
        }

        $max = (int) $sub->plan->max_analyses_month;
        $count = $this->countAnalysesInPeriod($sub);

        return max(0, $max - $count);
    }

    /**
     * Count the `analysis` UsageRecord rows attributable to a Subscription
     * within its current billing window (`period_start` ≤ recorded_at ≤
     * `period_end`).
     *
     * Kept as a private helper so `canRunAnalysis()` and
     * `remainingAnalyses()` share exactly one definition of "how many
     * analyses have I already consumed", preventing the two callers from
     * ever drifting apart.
     *
     * @param  Subscription $sub The Subscription whose usage is tallied.
     * @return int               Number of analysis UsageRecord rows in window.
     */
    private function countAnalysesInPeriod(Subscription $sub): int
    {
        return (int) UsageRecord::where('subscription_id', $sub->id)
            ->where('metric', 'analysis')
            ->whereBetween('recorded_at', [$sub->period_start, $sub->period_end])
            ->count();
    }
}
