<?php

declare(strict_types=1);

namespace Aero\MasterAds\Classes\Exceptions;

use Throwable;

/**
 * MetaApiRateLimitException
 *
 * Thrown by `MetaApiClient` after exhausting the exponential backoff retry
 * budget (waits of 1, 2, 4, 8 and 16 seconds — 5 retries) against Meta Graph
 * API errors with HTTP code 429 or Meta error code 613 (application
 * request-limit reached).
 *
 * Extends `MetaApiException` so callers that only care about "any Meta API
 * failure" can catch the parent type, while jobs and observability hooks
 * that need to react to rate-limit specifically can match on this subclass
 * and inspect `$retriesUsed`.
 *
 * The owning job/command should be left in a `failed` state on bubble-up
 * (per Requirement 3.7) so the scheduler can surface the issue.
 *
 * Validates: Requirements 3.7
 */
class MetaApiRateLimitException extends MetaApiException
{
    /**
     * @param  string           $message       Human-readable error message.
     * @param  int              $code          Exception code (typically 429 or the Meta sub-code 613).
     * @param  Throwable|null   $previous      Wrapped underlying exception from the last retry.
     * @param  array<string,mixed> $context    Structured diagnostic payload for logs (endpoint, request id, etc.).
     * @param  int              $retriesUsed   How many retry attempts were consumed before giving up.
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        array $context = [],
        public readonly int $retriesUsed = 0
    ) {
        parent::__construct($message, $code, $previous, $context);
    }
}
