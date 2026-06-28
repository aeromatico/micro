<?php

declare(strict_types=1);

namespace Aero\MasterAds\Classes\Exceptions;

use RuntimeException;
use Throwable;

/**
 * UnsupportedActionTypeException
 *
 * Thrown by `RecommendationApplier` (and any future applier-like component)
 * when the `Recommendation.action_type` is not applicable to the
 * `Recommendation.target_type` of the row being applied.
 *
 * Concretely raised when:
 *   - `action_type = change_audience` and the target is not an `Ad_Set`
 *     (Requirement 9.4), or
 *   - `action_type = change_creative` and the target is not an `Ad`
 *     (Requirement 9.4), or
 *   - the action type is unknown to the applier's dispatch table.
 *
 * The exception MUST be raised **before** any Graph API call so the Meta
 * side stays untouched and no `Applied_Action` row is persisted.
 *
 * `$actionType` carries the offending value so callers can render a precise
 * error and so log handlers can aggregate by action type.
 *
 * Validates: Requirements 9.4
 */
class UnsupportedActionTypeException extends RuntimeException
{
    /**
     * @param  string           $message    Human-readable error message.
     * @param  int              $code       Exception code (free-form, defaults to 0).
     * @param  Throwable|null   $previous   Wrapped underlying exception, if any.
     * @param  array<string,mixed> $context Structured diagnostic payload (recommendation_id, target_type, …).
     * @param  string           $actionType The unsupported action_type value (e.g. "change_audience").
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        public readonly array $context = [],
        public readonly string $actionType = ''
    ) {
        parent::__construct($message, $code, $previous);
    }
}
