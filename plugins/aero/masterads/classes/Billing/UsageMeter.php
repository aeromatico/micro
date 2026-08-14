<?php declare(strict_types=1);

namespace Aero\MasterAds\Classes\Billing;

use Aero\MasterAds\Models\Subscription;
use Aero\MasterAds\Models\UsageRecord;
use InvalidArgumentException;

/**
 * UsageMeter — records consumption events against a Subscription.
 *
 * Each call to `record()` inserts a brand-new `UsageRecord` row; the meter
 * never updates or upserts. This append-only design keeps the audit trail
 * intact and lets `PlanLimiter` answer "how much have I used this period?"
 * with a single deterministic `COUNT(*)` over the billing window.
 *
 * Callers and metrics:
 *   - `RecommendationEngine`     → `record($sub, 'analysis')` after a
 *                                  successful AI analysis (one row per run).
 *   - `SyncMetaAccountJob`       → `record($sub, 'sync')` after a successful
 *                                  Meta sync (one row per completed sync).
 *   - `RecommendationApplier`    → `record($sub, 'applied_action')` after a
 *                                  recommendation is applied successfully.
 *
 * Domain invariants enforced here (before the database is touched):
 *   1. `$metric` MUST be one of `analysis`, `sync`, `applied_action`.
 *   2. `$qty`    MUST be `>= 1` (you cannot record "zero" or "negative"
 *      usage; non-events are simply not recorded).
 *
 * Violations throw `\InvalidArgumentException`, surfacing a programmer
 * error to the caller before a row is ever persisted.
 *
 * Validates: Requirement 9.7.
 */
final class UsageMeter
{
    /**
     * The whitelisted set of metrics this meter accepts.
     *
     * Kept in sync with the `metric` validation rule on `UsageRecord` and
     * with the enum used by `PlanLimiter`.
     */
    private const ALLOWED_METRICS = ['analysis', 'sync', 'applied_action'];

    /**
     * Record a usage event against a Subscription.
     *
     * Always creates a new `UsageRecord` row (no upsert, no de-dup). The
     * `recorded_at` column is stamped with the current wall-clock time so
     * the event lands inside the Subscription's current billing window.
     *
     * Validates: Requirement 9.7.
     *
     * @param  Subscription $sub    The Subscription this event is billed to.
     * @param  string       $metric One of `analysis`, `sync`, `applied_action`.
     * @param  int          $qty    Strictly positive count (defaults to 1).
     * @return UsageRecord          The persisted UsageRecord row.
     *
     * @throws InvalidArgumentException When `$metric` is not whitelisted
     *                                  or `$qty` is less than 1.
     */
    public function record(Subscription $sub, string $metric, int $qty = 1): UsageRecord
    {
        if (!in_array($metric, self::ALLOWED_METRICS, true)) {
            throw new InvalidArgumentException(sprintf(
                'UsageMeter: invalid metric "%s"; expected one of: %s.',
                $metric,
                implode(', ', self::ALLOWED_METRICS)
            ));
        }

        if ($qty < 1) {
            throw new InvalidArgumentException(sprintf(
                'UsageMeter: qty must be >= 1, got %d.',
                $qty
            ));
        }

        /** @var UsageRecord $record */
        $record = UsageRecord::create([
            'subscription_id' => $sub->id,
            'metric'          => $metric,
            'qty'             => $qty,
            'recorded_at'     => now(),
        ]);

        return $record;
    }
}
