<?php declare(strict_types=1);

namespace Aero\MasterAds\Classes\Engine;

use Aero\MasterAds\Classes\Billing\PlanLimiter;
use Aero\MasterAds\Classes\Billing\UsageMeter;
use Aero\MasterAds\Classes\Exceptions\MetaApiException;
use Aero\MasterAds\Classes\Exceptions\UnsupportedActionTypeException;
use Aero\MasterAds\Classes\Meta\MetaApiClient;
use Aero\MasterAds\Models\Ad;
use Aero\MasterAds\Models\AdSet;
use Aero\MasterAds\Models\AppliedAction;
use Aero\MasterAds\Models\Campaign;
use Aero\MasterAds\Models\MetaAccount;
use Aero\MasterAds\Models\Recommendation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Throwable;

/**
 * RecommendationApplier — Idempotent application of an approved
 * {@see Recommendation} to the Meta Graph API, with full audit trail.
 *
 * Lifecycle of a single `apply()` call (see also pseudocode "Algoritmo 3"
 * in `design.md`):
 *
 *  1. **Idempotency short-circuit (P1, Requirements 7.2, 7.12, 19.5)** —
 *     if an {@see AppliedAction} with `recommendation_id = $rec->id` and
 *     `success = true` already exists, return it **without any Meta call**.
 *  2. **Target resolution** — walk `Recommendation → AiAnalysis → (target_type,
 *     target_id)` to load the concrete Campaign | AdSet | Ad row.
 *  3. **MetaAccount resolution** — walk the parent chain
 *     (`Campaign → MetaAccount`, `AdSet → Campaign → MetaAccount`,
 *     `Ad → AdSet → Campaign → MetaAccount`).
 *  4. **Action-type pre-check (Requirements 7.8, 7.9)** — fail fast with
 *     {@see UnsupportedActionTypeException} BEFORE any snapshot or write,
 *     so the Meta side stays untouched and no AppliedAction row is
 *     written on incompatible (`action_type`, `target_type`) tuples.
 *  5. **Snapshot `before_state` (Requirement 7.3)** — GET the relevant
 *     fields for the action type.
 *  6. **Dispatch by `action_type`** (Requirements 7.4–7.9) — POST the
 *     change to `/<meta_id>`. Budget figures are converted from the
 *     workspace decimal to Meta's minor-unit (cents) integer.
 *  7. **Snapshot `after_state` (Requirement 7.10)** — GET the same
 *     fields again so reviewers can diff before vs. after.
 *  8. **Persist success in `DB::transaction` (Requirements 7.10, 8.1,
 *     8.2, 15.5)** — write the AppliedAction, flip the Recommendation
 *     to `'applied'`, meter the consumption against the active
 *     Subscription, and fire `aero.masterads.recommendation_applied`.
 *  9. **On any failure during steps 5–7 (Requirement 7.11)** — persist
 *     an AppliedAction with `success = false`, flip the Recommendation
 *     to `'failed'`, and re-throw the original exception so the caller
 *     (job runner, controller) can surface it.
 *
 * Validates: Requirements 7.1, 7.2, 7.3, 7.4, 7.5, 7.6, 7.7, 7.8, 7.9,
 *            7.10, 7.11, 7.12, 8.1, 8.2, 15.5, 19.5.
 *
 * NOTE: `final` was removed (in favour of an explicit
 * {@see self::buildMetaClient()} testability hook) so the property test
 * for P1 can swap in a counting stub for {@see MetaApiClient}. Sub-classes
 * are still strongly discouraged in production code — the contract lives
 * on {@see RecommendationApplierInterface}, not on this class.
 */
class RecommendationApplier implements RecommendationApplierInterface
{
    /**
     * Field list for `GET /<meta_id>` snapshots when the action affects
     * budget or status (`adjust_budget`, `pause`, `resume`, `scale`).
     */
    private const FIELDS_BUDGET = 'status,daily_budget,lifetime_budget';

    /**
     * Field list for `GET /<meta_id>` snapshots when the action affects
     * targeting (`change_audience`).
     */
    private const FIELDS_TARGETING = 'targeting,status';

    /**
     * Field list for `GET /<meta_id>` snapshots when the action affects
     * the creative (`change_creative`).
     */
    private const FIELDS_CREATIVE = 'creative,status';

    /**
     * @param  UsageMeter $meter Billing meter resolved through the
     *                           container. Used to record an
     *                           `applied_action` UsageRecord against
     *                           the workspace's active Subscription on
     *                           every successful apply (Requirement 7.10).
     */
    public function __construct(
        private readonly UsageMeter $meter
    ) {
    }

    /**
     * Construct the {@see MetaApiClient} that will issue every Graph API
     * call for one `apply()` invocation. Isolated as a protected hook so
     * the P1 property test (`tests/Properties/ApplyIdempotencyTest`) can
     * subclass and inject a counting stub without touching the production
     * dispatch logic. Production code MUST NOT override this method.
     *
     * @param  MetaAccount $metaAccount  Resolved MetaAccount that owns the
     *                                   recommendation's target entity.
     * @return MetaApiClient
     */
    protected function buildMetaClient(MetaAccount $metaAccount): MetaApiClient
    {
        return new MetaApiClient($metaAccount);
    }

    /**
     * Apply an approved Recommendation. Idempotent on `(rec, success)`.
     *
     * See class docblock for the full lifecycle. Throws
     * {@see UnsupportedActionTypeException} for incompatible action/target
     * tuples (BEFORE any Meta call); throws the original Meta exception
     * (typically {@see MetaApiException}) after persisting a failed
     * AppliedAction.
     *
     * @param  Recommendation $rec
     * @param  int            $appliedByUserId
     * @return AppliedAction
     *
     * @throws UnsupportedActionTypeException
     * @throws MetaApiException
     */
    public function apply(Recommendation $rec, int $appliedByUserId): AppliedAction
    {
        // 1. Idempotency P1 — short-circuit without touching Meta.
        $existing = AppliedAction::where('recommendation_id', $rec->id)
            ->where('success', true)
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        // 2. Load target by walking Recommendation → AiAnalysis.
        $analysis = $rec->ai_analysis;
        if ($analysis === null) {
            throw new InvalidArgumentException(
                sprintf(
                    'Recommendation #%d has no AiAnalysis parent; cannot resolve target.',
                    (int) $rec->id
                )
            );
        }
        $target = $this->loadTarget((string) $analysis->target_type, (int) $analysis->target_id);

        // 3. Resolve the MetaAccount via the parent chain.
        $metaAccount = $this->resolveMetaAccount($target);

        // 4. Pre-check action_type validity BEFORE any Meta call so that
        //    UnsupportedActionTypeException never produces an AppliedAction
        //    (Requirements 7.8, 7.9).
        $this->assertActionTypeValid($rec, $target);

        // 5. Build the API client tied to the resolved MetaAccount.
        //    Delegated to a protected hook so tests can swap in a stub
        //    (see TestableRecommendationApplier in the P1 property test).
        $client = $this->buildMetaClient($metaAccount);

        $fields       = $this->fieldsForActionType((string) $rec->action_type);
        $beforeState  = null;
        $metaResponse = null;

        try {
            // 6. Snapshot before_state (Requirement 7.3).
            $beforeState = $client->call('GET', $target->meta_id, ['fields' => $fields]);

            // 7. Dispatch the write (Requirements 7.4–7.9).
            $metaResponse = $this->dispatchAction($client, $rec, $target, $beforeState);

            // 8. Snapshot after_state (Requirement 7.10).
            $afterState = $client->call('GET', $target->meta_id, ['fields' => $fields]);

            // 9. Persist success atomically (Requirements 7.10, 8.1, 8.2, 15.5).
            return DB::transaction(function () use (
                $rec,
                $appliedByUserId,
                $beforeState,
                $afterState,
                $metaResponse,
                $metaAccount
            ): AppliedAction {
                $action = AppliedAction::create([
                    'recommendation_id' => $rec->id,
                    'applied_by'        => $appliedByUserId,
                    'success'           => true,
                    'before_state'      => $beforeState,
                    'after_state'       => $afterState,
                    'meta_response'     => $metaResponse,
                ]);

                $rec->status = 'applied';
                $rec->save();

                // Meter consumption against the active Subscription.
                // PlanLimiter::activeSubscription returns null when the
                // workspace has no active or trialing subscription — in
                // that edge case we skip metering (Requirement 9.7 still
                // holds: only contable operations produce a UsageRecord).
                $workspace = $metaAccount->workspace;
                if ($workspace !== null) {
                    $sub = app(PlanLimiter::class)->activeSubscription($workspace);
                    if ($sub !== null) {
                        $this->meter->record($sub, 'applied_action', 1);
                    }
                }

                Event::dispatch('aero.masterads.recommendation_applied', [$rec, $action]);

                return $action;
            });
        } catch (Throwable $e) {
            // 10. Persist failure for auditability (Requirement 7.11, 8.1).
            //     The throw of UnsupportedActionTypeException happens BEFORE
            //     this try/catch, so a failed AppliedAction is never written
            //     for an incompatible action/target tuple.
            AppliedAction::create([
                'recommendation_id' => $rec->id,
                'applied_by'        => $appliedByUserId,
                'success'           => false,
                'before_state'      => $beforeState,
                'after_state'       => null,
                'meta_response'     => [
                    'error'   => $e->getMessage(),
                    'context' => $e instanceof MetaApiException ? $e->context : [],
                ],
            ]);

            $rec->status = 'failed';
            $rec->save();

            throw $e;
        }
    }

    /**
     * Resolve the concrete target model from a `(target_type, target_id)` pair.
     *
     * @param  string $targetType One of `campaign|adset|ad`.
     * @param  int    $targetId   Primary key of the target row.
     * @return Campaign|AdSet|Ad
     *
     * @throws InvalidArgumentException When `$targetType` is not one of the
     *         three known values. The exception is raised BEFORE any Meta
     *         call so callers get the same "fail fast" guarantee as for
     *         {@see UnsupportedActionTypeException}.
     */
    private function loadTarget(string $targetType, int $targetId)
    {
        return match ($targetType) {
            'campaign' => Campaign::findOrFail($targetId),
            'adset'    => AdSet::findOrFail($targetId),
            'ad'       => Ad::findOrFail($targetId),
            default    => throw new InvalidArgumentException(
                sprintf('Unknown target_type "%s"', $targetType)
            ),
        };
    }

    /**
     * Walk the parent chain to the MetaAccount that owns `$target`.
     *
     * - Campaign → meta_account
     * - AdSet    → campaign → meta_account
     * - Ad       → ad_set   → campaign → meta_account
     *
     * @param  Campaign|AdSet|Ad $target
     * @return MetaAccount
     *
     * @throws InvalidArgumentException When `$target` is not one of the
     *         three expected models — a defensive guard so a future
     *         refactor that adds a new target type fails loudly.
     */
    private function resolveMetaAccount($target): MetaAccount
    {
        if ($target instanceof Campaign) {
            return $target->meta_account;
        }
        if ($target instanceof AdSet) {
            return $target->campaign->meta_account;
        }
        if ($target instanceof Ad) {
            return $target->ad_set->campaign->meta_account;
        }
        throw new InvalidArgumentException(
            sprintf('Cannot resolve MetaAccount for target of type %s', $target::class)
        );
    }

    /**
     * Reject incompatible `(action_type, target_type)` tuples BEFORE any
     * Meta call (Requirements 7.8, 7.9). Also catches an unknown action
     * type so the dispatch table stays exhaustive.
     *
     * @param  Recommendation     $rec
     * @param  Campaign|AdSet|Ad  $target
     *
     * @throws UnsupportedActionTypeException
     */
    private function assertActionTypeValid(Recommendation $rec, $target): void
    {
        switch ((string) $rec->action_type) {
            case 'adjust_budget':
            case 'pause':
            case 'resume':
            case 'scale':
                return;
            case 'change_audience':
                if (!$target instanceof AdSet) {
                    throw new UnsupportedActionTypeException(
                        'change_audience requires an AdSet target',
                        0,
                        null,
                        [
                            'recommendation_id' => (int) $rec->id,
                            'target_type'       => $target::class,
                        ],
                        'change_audience'
                    );
                }
                return;
            case 'change_creative':
                if (!$target instanceof Ad) {
                    throw new UnsupportedActionTypeException(
                        'change_creative requires an Ad target',
                        0,
                        null,
                        [
                            'recommendation_id' => (int) $rec->id,
                            'target_type'       => $target::class,
                        ],
                        'change_creative'
                    );
                }
                return;
            default:
                throw new UnsupportedActionTypeException(
                    'Unknown action_type',
                    0,
                    null,
                    ['recommendation_id' => (int) $rec->id],
                    (string) $rec->action_type
                );
        }
    }

    /**
     * Resolve the Meta fields list used for `before_state` / `after_state`
     * snapshots, selecting the columns that actually move under the
     * specific action type so the audit diff is meaningful and compact.
     *
     * @param  string $actionType
     * @return string
     */
    private function fieldsForActionType(string $actionType): string
    {
        return match ($actionType) {
            'change_audience' => self::FIELDS_TARGETING,
            'change_creative' => self::FIELDS_CREATIVE,
            default           => self::FIELDS_BUDGET,
        };
    }

    /**
     * Issue the write to Meta and return its decoded response.
     *
     * Each case implements one of Requirements 7.4–7.9; the action-type
     * compatibility was already enforced by
     * {@see self::assertActionTypeValid()}, so this method assumes a
     * valid tuple. The trailing `throw` is defensive: it should be
     * unreachable but guarantees the method always returns or throws.
     *
     * @param  MetaApiClient      $client
     * @param  Recommendation     $rec
     * @param  Campaign|AdSet|Ad  $target
     * @param  array<string,mixed> $beforeState  Used by `scale` to compute
     *                                            the new budget against the
     *                                            current Meta-side value.
     * @return array<string,mixed>                Decoded Meta response.
     *
     * @throws MetaApiException
     */
    private function dispatchAction(
        MetaApiClient $client,
        Recommendation $rec,
        $target,
        array $beforeState
    ): array {
        switch ((string) $rec->action_type) {
            case 'adjust_budget':
                // payload.daily_budget is decimal in workspace currency;
                // Meta wants minor units (cents) as integer.
                return $client->call('POST', $target->meta_id, [
                    'daily_budget' => (int) (((float) ($rec->payload['daily_budget'] ?? 0)) * 100),
                ]);

            case 'pause':
                return $client->call('POST', $target->meta_id, ['status' => 'PAUSED']);

            case 'resume':
                return $client->call('POST', $target->meta_id, ['status' => 'ACTIVE']);

            case 'scale':
                // before_state.daily_budget is already in cents (raw Meta
                // payload). multiplier scales it; result stays in cents.
                $multiplier = (float) ($rec->payload['multiplier'] ?? 1.0);
                $currentBudget = (int) ($beforeState['daily_budget'] ?? 0);
                $newBudget = (int) ($currentBudget * $multiplier);
                return $client->call('POST', $target->meta_id, [
                    'daily_budget' => $newBudget,
                ]);

            case 'change_audience':
                return $client->call('POST', $target->meta_id, [
                    'targeting' => json_encode($rec->payload['targeting'] ?? []),
                ]);

            case 'change_creative':
                return $client->call('POST', $target->meta_id, [
                    'creative' => json_encode([
                        'creative_id' => $rec->payload['creative_id'] ?? null,
                    ]),
                ]);
        }

        // Defensive: should be unreachable thanks to assertActionTypeValid().
        throw new UnsupportedActionTypeException(
            'Unknown action_type',
            0,
            null,
            ['recommendation_id' => (int) $rec->id],
            (string) $rec->action_type
        );
    }
}
