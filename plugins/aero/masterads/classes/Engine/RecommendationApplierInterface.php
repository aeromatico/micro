<?php declare(strict_types=1);

namespace Aero\MasterAds\Classes\Engine;

use Aero\MasterAds\Models\AppliedAction;
use Aero\MasterAds\Models\Recommendation;

/**
 * RecommendationApplierInterface — Contract for the service that applies a
 * Recommendation to Meta and persists the audit trail.
 *
 * The interface exists so the Engine layer can be wired through the DI
 * container (jobs, console commands, controllers) without coupling the
 * callers to a concrete implementation. The default implementation lives in
 * {@see RecommendationApplier}; tests may bind a fake against this contract.
 *
 * Validates: Requirements 7.1, 7.2, 7.12, 19.5.
 */
interface RecommendationApplierInterface
{
    /**
     * Apply an approved Recommendation to Meta. The operation is
     * **idempotent** on the tuple (recommendation_id, success): a second
     * invocation with a Recommendation that already has an AppliedAction
     * with `success = true` returns the existing AppliedAction **without
     * issuing any Meta Graph API call** (Requirement 7.2, 7.12, 19.5).
     *
     * On success, the implementation persists an AppliedAction with both
     * `before_state` and `after_state` snapshots and moves the
     * Recommendation to `status = 'applied'`. On failure, an AppliedAction
     * with `success = false` is written for auditability and the
     * Recommendation is moved to `status = 'failed'` before the original
     * exception is re-thrown.
     *
     * @param  Recommendation $rec               The approved Recommendation
     *                                           to apply. The implementation
     *                                           expects `$rec->status` to be
     *                                           `'approved'`; enforcement of
     *                                           the precondition lies with
     *                                           the caller.
     * @param  int            $appliedByUserId   Backend_User id authoring
     *                                           the application (persisted
     *                                           in `applied_by` for audit).
     * @return AppliedAction                     The audit row created (or
     *                                           the existing one when
     *                                           idempotency short-circuits).
     *
     * @throws \Aero\MasterAds\Classes\Exceptions\UnsupportedActionTypeException
     *         When the Recommendation's `action_type` is not compatible
     *         with the target_type (`change_audience` outside an AdSet,
     *         `change_creative` outside an Ad) or is unknown to the
     *         dispatch table. Thrown BEFORE any Meta API call so the Meta
     *         side stays untouched and no AppliedAction row is written.
     * @throws \Aero\MasterAds\Classes\Exceptions\MetaApiException
     *         When the Meta Graph API returns an error during snapshot or
     *         dispatch. A failed AppliedAction is persisted before the
     *         exception is re-thrown.
     */
    public function apply(Recommendation $rec, int $appliedByUserId): AppliedAction;
}
