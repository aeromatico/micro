<?php

declare(strict_types=1);

namespace Aero\MasterAds\Classes\Exceptions;

use RuntimeException;
use Throwable;

/**
 * MetaApiException
 *
 * Generic error raised when a call to the Meta Graph API fails for any reason
 * that is not specifically modeled by a more precise subclass.
 *
 * Thrown by `MetaApiClient` and the higher-level services that use it
 * (`MetaSyncService`, `RecommendationApplier`, …) when Meta returns a 4xx/5xx
 * response, a malformed body, or the request cannot be sent.
 *
 * The `$context` array typically carries `endpoint`, `http_status`,
 * `meta_error_code`, `meta_error_subcode` and the request id, so callers
 * and log handlers can correlate the failure with Meta's server logs.
 *
 * Specialized subclasses (e.g. `MetaApiRateLimitException`) extend this
 * class so consumers can catch the broad case or the specific one.
 *
 * Validates: Requirements 3.7
 */
class MetaApiException extends RuntimeException
{
    /**
     * @param  string           $message   Human-readable error message.
     * @param  int              $code      Exception code (typically the HTTP status returned by Meta).
     * @param  Throwable|null   $previous  Wrapped underlying exception (e.g. Guzzle transport error).
     * @param  array<string,mixed> $context Structured diagnostic payload for logs.
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        public readonly array $context = []
    ) {
        parent::__construct($message, $code, $previous);
    }
}
