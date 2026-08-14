<?php

declare(strict_types=1);

namespace Aero\MasterAds\Classes\Exceptions;

use RuntimeException;
use Throwable;

/**
 * AiProviderException
 *
 * Thrown by AI provider clients (OpenAI, Anthropic, …) when:
 *   - the network request to the provider fails,
 *   - the provider returns a rate-limit (HTTP 429) after retries are exhausted,
 *   - the provider returns a 5xx error,
 *   - the response body cannot be parsed into the expected JSON schema, or
 *   - the response does not satisfy the requested JSON schema.
 *
 * Caught by `RecommendationEngine::run()` so it can mark the `Ai_Analysis`
 * as `failed`, persist `error_message`, and skip both the `Usage_Record`
 * write and any orphan `Recommendation` rows (per Requirement 6.9).
 *
 * The `$context` array typically carries `provider`, `model`,
 * `http_status`, `request_id` and a truncated body excerpt for diagnostics.
 *
 * Validates: Requirements 6.9
 */
class AiProviderException extends RuntimeException
{
    /**
     * @param  string           $message   Human-readable error message.
     * @param  int              $code      Exception code (typically the upstream HTTP status).
     * @param  Throwable|null   $previous  Wrapped underlying exception (transport, JSON decode, etc.).
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
