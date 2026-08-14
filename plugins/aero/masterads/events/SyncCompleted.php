<?php declare(strict_types=1);

namespace Aero\MasterAds\Events;

/**
 * SyncCompleted
 *
 * Domain event dispatched when a `SyncMetaAccountJob` finishes successfully
 * after upserting all Campaigns, AdSets and Ads for the target Meta_Account
 * and bumping its `last_synced_at` timestamp.
 *
 * Fired by: `Aero\MasterAds\Jobs\SyncMetaAccountJob` once the entity sync
 * loop drains every `paging.next` cursor without error and the subsequent
 * `SyncInsightsJob` is queued (see Requirements 3.4, 3.5).
 *
 * Payload:
 *   - `metaAccount`: the Meta_Account whose entities have just been
 *     synchronised. Consumers can read `last_synced_at` to know the
 *     watermark of the completed sync.
 *
 * Note: existing code paths dispatch this milestone using the legacy string
 * event name `aero.masterads.sync_completed`. This class exists for type-safe
 * future use; `Plugin::boot()` (task 15.5) registers both the string-based
 * and class-based listeners.
 *
 * Validates: Requirements 3.4, 13.2
 */
final class SyncCompleted
{
    public function __construct(
        public readonly \Aero\MasterAds\Models\MetaAccount $metaAccount,
    ) {
    }
}
