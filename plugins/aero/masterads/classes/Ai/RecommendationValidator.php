<?php declare(strict_types=1);

namespace Aero\MasterAds\Classes\Ai;

use Aero\MasterAds\Models\Ad;
use Aero\MasterAds\Models\AdSet;

/**
 * RecommendationValidator
 *
 * Validates a single recommendation item — already shape-checked by
 * {@see ResponseParser::parse()} — against the action-specific payload
 * schema and the target type. Implements Requirements 6.6 and 6.7:
 *
 *   - 6.6: the Recommendation_Engine SHALL discard recommendations whose
 *          payload does not validate against the schema of its `action_type`
 *          before persisting the survivors with `status = pending`.
 *   - 6.7: every persisted Recommendation SHALL carry a valid
 *          `action_type`, `severity` and non-empty `rationale`.
 *
 * The validator is intentionally pure: it never mutates the input, never
 * touches the database, and returns a boolean. The caller
 * (Recommendation_Engine) decides what to do with the rejection (skip and
 * continue with the rest of the batch — never abort the whole analysis).
 *
 * Validates: Requirements 6.6, 6.7
 */
final class RecommendationValidator
{
    /** Allowed severities (must mirror {@see PromptBuilder::SEVERITIES}). */
    private const SEVERITIES = ['low', 'medium', 'high', 'critical'];

    /** Minimum length for `rationale` (Requirement 6.7 — non-empty + meaningful). */
    private const RATIONALE_MIN_LENGTH = 10;

    /** Upper bound on `scale.multiplier` to prevent runaway budget changes. */
    private const SCALE_MULTIPLIER_MAX = 10.0;

    /**
     * Validate the recommendation against the common rules AND the
     * action-specific payload schema.
     *
     * Common rules (Requirement 6.7):
     *   - `severity` ∈ {low, medium, high, critical}
     *   - `rationale` is a string with length ≥ 10
     *   - `confidence` (if present) is numeric in [0, 100]
     *   - `expected_impact_pct` (if present) is numeric
     *
     * Per-action_type rules (Requirement 6.6):
     *   - adjust_budget:    payload.daily_budget numeric > 0
     *   - pause / resume:   payload may be empty `{}`
     *   - scale:            payload.multiplier numeric > 0 and ≤ 10
     *   - change_audience:  target instanceof AdSet; payload.targeting non-empty array
     *   - change_creative:  target instanceof Ad; payload.creative_id non-empty string
     *                       OR payload.creative non-empty array
     *   - any other action_type: return false
     *
     * @param  array<string,mixed> $rec    Schema-shaped recommendation (action_type, severity, rationale, payload).
     * @param  mixed               $target Eloquent model for the recommendation subject
     *                                     (Campaign | AdSet | Ad). Type-checked per action_type.
     * @return bool                        True if the recommendation is safe to persist.
     */
    public function validate(array $rec, $target): bool
    {
        // --- Common rules (Requirement 6.7) ---
        if (!$this->validateCommon($rec)) {
            return false;
        }

        $payload = $rec['payload'] ?? null;
        if (!is_array($payload)) {
            return false;
        }

        $actionType = $rec['action_type'];

        // --- Per-action_type rules (Requirement 6.6) ---
        switch ($actionType) {
            case 'adjust_budget':
                return $this->validateAdjustBudget($payload);

            case 'pause':
            case 'resume':
                // Empty payload is allowed for pause/resume.
                return true;

            case 'scale':
                return $this->validateScale($payload);

            case 'change_audience':
                return $target instanceof AdSet
                    && $this->validateChangeAudience($payload);

            case 'change_creative':
                return $target instanceof Ad
                    && $this->validateChangeCreative($payload);

            default:
                // Unknown action_type — fail closed.
                return false;
        }
    }

    /**
     * Apply the rules common to every action_type.
     *
     * @param  array<string,mixed> $rec
     */
    private function validateCommon(array $rec): bool
    {
        // action_type must be a non-empty string (enum check is per-branch).
        $actionType = $rec['action_type'] ?? null;
        if (!is_string($actionType) || $actionType === '') {
            return false;
        }

        // severity ∈ enum
        $severity = $rec['severity'] ?? null;
        if (!is_string($severity) || !in_array($severity, self::SEVERITIES, true)) {
            return false;
        }

        // rationale is a string with length ≥ 10
        $rationale = $rec['rationale'] ?? null;
        if (!is_string($rationale) || mb_strlen(trim($rationale)) < self::RATIONALE_MIN_LENGTH) {
            return false;
        }

        // confidence (optional) ∈ [0, 100]
        if (array_key_exists('confidence', $rec)) {
            $confidence = $rec['confidence'];
            if (!is_numeric($confidence)) {
                return false;
            }
            $c = (float) $confidence;
            if ($c < 0.0 || $c > 100.0) {
                return false;
            }
        }

        // expected_impact_pct (optional) is numeric (can be negative for downside risks)
        if (array_key_exists('expected_impact_pct', $rec)) {
            if (!is_numeric($rec['expected_impact_pct'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * adjust_budget: payload.daily_budget must be numeric > 0.
     *
     * @param  array<string,mixed> $payload
     */
    private function validateAdjustBudget(array $payload): bool
    {
        $value = $payload['daily_budget'] ?? null;
        if (!is_numeric($value)) {
            return false;
        }
        return ((float) $value) > 0.0;
    }

    /**
     * scale: payload.multiplier must be numeric > 0 and ≤ 10.
     *
     * @param  array<string,mixed> $payload
     */
    private function validateScale(array $payload): bool
    {
        $value = $payload['multiplier'] ?? null;
        if (!is_numeric($value)) {
            return false;
        }
        $multiplier = (float) $value;
        return $multiplier > 0.0 && $multiplier <= self::SCALE_MULTIPLIER_MAX;
    }

    /**
     * change_audience: payload.targeting must be a non-empty array.
     *
     * @param  array<string,mixed> $payload
     */
    private function validateChangeAudience(array $payload): bool
    {
        $targeting = $payload['targeting'] ?? null;
        return is_array($targeting) && $targeting !== [];
    }

    /**
     * change_creative: payload.creative_id non-empty string OR
     *                  payload.creative non-empty array.
     *
     * @param  array<string,mixed> $payload
     */
    private function validateChangeCreative(array $payload): bool
    {
        $creativeId = $payload['creative_id'] ?? null;
        if (is_string($creativeId) && trim($creativeId) !== '') {
            return true;
        }

        $creative = $payload['creative'] ?? null;
        return is_array($creative) && $creative !== [];
    }
}
