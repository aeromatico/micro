<?php declare(strict_types=1);

namespace Aero\MasterAds\Events;

/**
 * MetaTokenRefreshFailed
 *
 * Domain event dispatched when `Meta_Token_Refresher` cannot renew an
 * about-to-expire `access_token` for a Meta_Account (Requirement 2.7).
 *
 * Fired by: `Aero\MasterAds\Classes\Meta\MetaTokenRefresher` when the Graph
 * refresh endpoint returns an error, the long-lived token cannot be
 * exchanged, or `expires_at` cannot be pushed beyond `now() + 30 days`.
 * Operators rely on this event to detect Meta_Accounts that require manual
 * reconnection before scheduled jobs start failing en masse.
 *
 * Payload:
 *   - `metaAccount`: the Meta_Account whose token refresh failed. Tokens
 *     remain encrypted; consumers MUST NOT log decrypted credentials
 *     (Requirement 15.x).
 *   - `errorMessage`: human-readable description of the failure (Graph API
 *     error message, exception summary, etc.). Empty string when no
 *     additional context is available.
 *
 * Note: existing code paths dispatch this milestone using the legacy string
 * event name `aero.masterads.meta_token_refresh_failed`. This class exists
 * for type-safe future use; `Plugin::boot()` (task 15.5) registers both the
 * string-based and class-based listeners.
 *
 * Validates: Requirements 2.7, 13.x (observability hook for token refresh)
 */
final class MetaTokenRefreshFailed
{
    public function __construct(
        public readonly \Aero\MasterAds\Models\MetaAccount $metaAccount,
        public readonly string $errorMessage = '',
    ) {
    }
}
