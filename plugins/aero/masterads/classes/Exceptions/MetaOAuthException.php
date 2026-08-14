<?php

declare(strict_types=1);

namespace Aero\MasterAds\Classes\Exceptions;

use RuntimeException;
use Throwable;

/**
 * MetaOAuthException
 *
 * Thrown when the Meta OAuth `code` → `access_token` exchange fails, either
 * because Meta returned an error payload, the request was malformed, or the
 * network call could not be completed.
 *
 * Specifically raised by `MetaOAuthService::exchangeCode()` so the OAuth
 * callback handler can roll back any partially persisted Meta_Account state
 * inside an atomic transaction.
 *
 * The `$context` array carries diagnostic data (e.g. `meta_error_code`,
 * `meta_error_subcode`, `redirect_uri`) intended for logging — never for
 * end-user display.
 *
 * Validates: Requirements 2.5
 */
class MetaOAuthException extends RuntimeException
{
    /**
     * Default human-readable message used when the caller does not supply one.
     */
    public const MSG_DEFAULT = 'Meta OAuth exchange failed';

    /**
     * @param  string           $message   Human-readable error message.
     * @param  int              $code      Exception code (often the upstream HTTP status).
     * @param  Throwable|null   $previous  Wrapped underlying exception (e.g. Guzzle error).
     * @param  array<string,mixed> $context Structured diagnostic payload for logs.
     */
    public function __construct(
        string $message = self::MSG_DEFAULT,
        int $code = 0,
        ?Throwable $previous = null,
        public readonly array $context = []
    ) {
        parent::__construct($message, $code, $previous);
    }
}
