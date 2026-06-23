<?php declare(strict_types=1);

namespace Aero\MasterAds\Events;

/**
 * MetaAccountConnected
 *
 * Domain event dispatched when a Meta_Account is successfully connected to
 * a Workspace via OAuth (i.e. the `code` was exchanged for a long-lived
 * access_token and the Meta_Account row was persisted).
 *
 * Fired by: `Aero\MasterAds\Classes\Meta\MetaOAuthService` immediately after
 * the Meta_Account is created (or its tokens refreshed on the
 * `meta_act_id` + `workspace_id` upsert path defined in Requirement 2.6).
 *
 * Payload:
 *   - `metaAccount`: the freshly persisted/updated Meta_Account model
 *     (tokens already encrypted via `Crypt::encrypt`, see Requirement 2.2).
 *
 * Note: existing code paths dispatch this milestone using the legacy string
 * event name `aero.masterads.meta_account_connected`. This class exists for
 * type-safe future use; `Plugin::boot()` (task 15.5) registers both the
 * string-based and class-based listeners.
 *
 * Validates: Requirements 2.8, 13.1
 */
final class MetaAccountConnected
{
    public function __construct(
        public readonly \Aero\MasterAds\Models\MetaAccount $metaAccount,
    ) {
    }
}
