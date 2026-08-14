<?php

declare(strict_types=1);

namespace Aero\MasterAds\Classes\Exceptions;

use RuntimeException;
use Throwable;

/**
 * QuotaExceededException
 *
 * Thrown by `PlanLimiter` whenever an operation would push a workspace past
 * the cap defined by its `Plan` for a given metric.
 *
 * Concretely raised when:
 *   - `PlanLimiter::canRunAnalysis($workspace)` returns `false` and the
 *     `RecommendationEngine` is about to create a new `Ai_Analysis`
 *     (Requirement 6.3), or
 *   - the workspace would exceed its `meta_account` connection quota
 *     during the OAuth callback flow.
 *
 * `$metric` identifies which quota was breached (e.g. `"analysis"`,
 * `"meta_account"`, `"applied_action"`), letting callers translate the
 * exception into a precise user-facing message and letting log handlers
 * aggregate by metric.
 *
 * No `Usage_Record` is written when this exception bubbles up (per
 * Requirement 6.3).
 *
 * Validates: Requirements 7.8, 7.9
 */
class QuotaExceededException extends RuntimeException
{
    /**
     * @param  string           $message   Human-readable error message.
     * @param  int              $code      Exception code (free-form, defaults to 0).
     * @param  Throwable|null   $previous  Wrapped underlying exception, if any.
     * @param  array<string,mixed> $context Structured diagnostic payload (workspace_id, current usage, limit, …).
     * @param  string           $metric    Quota dimension that was exceeded (e.g. "analysis", "meta_account").
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        public readonly array $context = [],
        public readonly string $metric = ''
    ) {
        parent::__construct($message, $code, $previous);
    }
}
