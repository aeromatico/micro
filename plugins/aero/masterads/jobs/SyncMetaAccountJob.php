<?php declare(strict_types=1);

namespace Aero\MasterAds\Jobs;

use Aero\MasterAds\Classes\Meta\MetaApiClient;
use Aero\MasterAds\Classes\Meta\MetaTokenRefresher;
use Aero\MasterAds\Models\Ad;
use Aero\MasterAds\Models\AdSet;
use Aero\MasterAds\Models\Campaign;
use Aero\MasterAds\Models\MetaAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * SyncMetaAccountJob — incremental sync of one connected Meta ad account.
 *
 * Walks Meta's Graph API for the entire `Campaign → AdSet → Ad` tree under a
 * single `MetaAccount` and upserts every payload into the local DB using the
 * Meta-side `id` as the natural key, so the operation is idempotent across
 * retries and queue redelivery (Requirement 19.4).
 *
 * Pipeline (mirrors `design.md` Algoritmo 1):
 *
 *   1. Generate a per-execution `correlation_id` (UUID v4) and stamp every
 *      log line with it plus `meta_account_id`, so an operator can stitch
 *      together the full run from the OctoberCMS log channel
 *      (Requirement 16.1).
 *   2. If the access token expires within 7 days, delegate to
 *      {@see MetaTokenRefresher::refresh()} and reload the model so any
 *      downstream HTTP call sees the freshly-rotated token. (Token rotation
 *      defense-in-depth — the underlying `MetaApiClient` does the same
 *      check, but doing it explicitly here makes the job's contract
 *      auditable in isolation.)
 *   3. Walk `/<meta_act_id>/campaigns` page-by-page via
 *      {@see MetaApiClient::getPaginated()} (a generator that follows
 *      `paging.next` lazily — never loads the full response into memory,
 *      Requirement 14.1) and upsert each item through
 *      {@see Campaign::upsertByMetaId()} (Requirement 3.1, 3.2).
 *   4. For every persisted Campaign, walk `<campaign.meta_id>/adsets` and
 *      upsert through {@see AdSet::upsertByMetaId()}.
 *   5. For every persisted AdSet, walk `<adset.meta_id>/ads` and upsert
 *      through {@see Ad::upsertByMetaId()}.
 *   6. Stamp `MetaAccount.last_synced_at = now()`, clear `last_error` and
 *      persist (Requirement 3.4).
 *   7. Dispatch the `aero.masterads.sync_completed` event carrying the
 *      `MetaAccount` so listeners (notifications, analytics) can react
 *      (Requirement 3.4 / design events table).
 *   8. Enqueue a {@see SyncInsightsJob} for the window
 *      `[last_synced_at - 30d, now()]` so the metrics fetch runs in its
 *      own queue slot without inflating this job's runtime
 *      (Requirements 3.5, 10.1, 10.2).
 *
 * Queue semantics:
 *   - Runs exclusively on the Redis queue worker — never inline in the
 *     HTTP request — see {@see ShouldQueue} (Requirement 19.1).
 *   - `$tries = 3`: at most three attempts before the job is moved to
 *     `failed_jobs`; combined with idempotent upserts this is safe.
 *   - `$timeout = 600` (10 min): a full sync over an account with
 *     thousands of ads can take several minutes; we cap it so a stuck
 *     job does not hold a worker indefinitely.
 *   - `failed()` persists the exception message into `MetaAccount.last_error`
 *     so it surfaces in the backend UI without forcing the operator to
 *     read the queue log (Requirements 10.7, 16.1 / 16.4).
 *
 * Tenancy: the queue worker has no `BackendAuth` user, so the
 * `BelongsToTenantScope` global scope is a no-op (see the trait's
 * `bootBelongsToTenantScope`) — therefore loading `Campaign`/`AdSet` rows
 * inside this job is not silently filtered.
 *
 * Validates: Requirements 3.1, 3.2, 3.4, 3.5, 10.1, 10.2, 10.7, 16.1, 19.4
 * (master-ads spec).
 */
final class SyncMetaAccountJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Maximum number of attempts before the job is sent to `failed_jobs`.
     * Idempotent upserts (Requirement 19.4) make retries safe.
     */
    public int $tries = 3;

    /**
     * Hard cap (seconds) for a single attempt. Meta sync over an account
     * with many campaigns is slow; 10 minutes gives the worker enough room
     * without letting a stuck attempt block its queue slot indefinitely.
     */
    public int $timeout = 600;

    /**
     * @param MetaAccount $metaAccount The connected ad account to sync.
     *                                  Eloquent will serialize only its
     *                                  primary key and reload the model when
     *                                  the job is unserialized by the worker
     *                                  (via {@see SerializesModels}).
     */
    public function __construct(public readonly MetaAccount $metaAccount)
    {
    }

    /**
     * Run the sync.
     *
     * Both parameters are optional injection seams used by tests — when
     * `null`, the job builds the client locally and resolves the refresher
     * through the container.
     *
     * @param MetaApiClient|null      $client    Pre-built API client. When
     *                                            null, a client bound to
     *                                            this job's `MetaAccount` is
     *                                            constructed.
     * @param MetaTokenRefresher|null $refresher Optional refresher override.
     *                                            When null the container is
     *                                            asked to build one.
     */
    public function handle(?MetaApiClient $client = null, ?MetaTokenRefresher $refresher = null): void
    {
        $correlationId = (string) Str::uuid();

        Log::info('[MasterAds][SyncMetaAccount] started', [
            'meta_account_id' => $this->metaAccount->id,
            'correlation_id'  => $correlationId,
        ]);

        // ── 1. Proactive token refresh ──────────────────────────────────
        // Mirrors Requirement 2.7 / 15.6: rotate when <7 days remaining so
        // the long sync below does not start with a near-expired token.
        if ($this->metaAccount->expiresWithinDays(7)) {
            ($refresher ?? app(MetaTokenRefresher::class))->refresh($this->metaAccount);
            // Reload to pick up the freshly-persisted token + expires_at.
            $this->metaAccount->refresh();
        }

        // ── 2. Build the API client ─────────────────────────────────────
        $client ??= new MetaApiClient($this->metaAccount);

        // ── 3. Sync campaigns ───────────────────────────────────────────
        $campaignCount = 0;
        $campaignFields = 'id,name,objective,status,daily_budget,lifetime_budget,start_time,stop_time';
        foreach ($client->getPaginated(
            $this->metaAccount->meta_act_id . '/campaigns',
            ['fields' => $campaignFields]
        ) as $payload) {
            Campaign::upsertByMetaId($payload, $this->metaAccount->id);
            $campaignCount++;
        }

        // ── 4. Sync adsets for every persisted campaign ────────────────
        // We re-query rather than reuse the API payload to ensure we have
        // the local PK (`campaign.id`) needed as the parent FK on upsert.
        $adSetCount = 0;
        $adSetFields = 'id,name,status,targeting,daily_budget,optimization_goal,bid_strategy';
        $campaigns = Campaign::query()
            ->where('meta_account_id', $this->metaAccount->id)
            ->get();
        foreach ($campaigns as $campaign) {
            foreach ($client->getPaginated(
                $campaign->meta_id . '/adsets',
                ['fields' => $adSetFields]
            ) as $payload) {
                AdSet::upsertByMetaId($payload, $campaign->id);
                $adSetCount++;
            }
        }

        // ── 5. Sync ads for every persisted adset ──────────────────────
        $adCount = 0;
        $adFields = 'id,name,status,creative';
        $adSets = AdSet::query()
            ->whereIn('campaign_id', $campaigns->pluck('id'))
            ->get();
        foreach ($adSets as $adSet) {
            foreach ($client->getPaginated(
                $adSet->meta_id . '/ads',
                ['fields' => $adFields]
            ) as $payload) {
                Ad::upsertByMetaId($payload, $adSet->id);
                $adCount++;
            }
        }

        // ── 6. Mark sync completed on the account ──────────────────────
        $this->metaAccount->last_synced_at = now();
        $this->metaAccount->last_error = null;
        $this->metaAccount->save();

        // ── 7. Domain event ────────────────────────────────────────────
        Event::dispatch('aero.masterads.sync_completed', [$this->metaAccount]);

        // ── 8. Enqueue insights fetch for the [cursor-30d, now] window ─
        // We always look back 30 days from the cursor so retroactive
        // updates Meta applies to historic metrics (attribution windows,
        // late-arriving conversions) are picked up on the next pass.
        // `copy()` defends against Carbon's in-place mutation.
        $insightsFrom = $this->metaAccount->last_synced_at !== null
            ? $this->metaAccount->last_synced_at->copy()->subDays(30)
            : now()->subDays(30);

        SyncInsightsJob::dispatch(
            $this->metaAccount->id,
            $insightsFrom,
            now()
        );

        // ── 9. Final success log with metric counts ────────────────────
        Log::info('[MasterAds][SyncMetaAccount] completed', [
            'meta_account_id' => $this->metaAccount->id,
            'correlation_id'  => $correlationId,
            'campaigns'       => $campaignCount,
            'ad_sets'         => $adSetCount,
            'ads'             => $adCount,
        ]);
    }

    /**
     * Invoked by Laravel's queue worker after the final failed attempt.
     *
     * Persists the failure message into `MetaAccount.last_error` so the
     * Workspace_Owner sees it on the backend list view, and logs it via the
     * configured channel (Requirements 10.7, 16.1).
     */
    public function failed(Throwable $e): void
    {
        Log::error('[MasterAds][SyncMetaAccount] failed', [
            'meta_account_id' => $this->metaAccount->id,
            'error'           => $e->getMessage(),
        ]);

        try {
            $this->metaAccount->last_error = $e->getMessage();
            $this->metaAccount->save();
        } catch (Throwable $persistError) {
            // Never let a secondary failure mask the original exception in
            // the queue log — surface the persistence error separately.
            Log::warning('[MasterAds][SyncMetaAccount] could not persist last_error', [
                'meta_account_id' => $this->metaAccount->id,
                'persist_error'   => $persistError->getMessage(),
                'original_error'  => $e->getMessage(),
            ]);
        }
    }
}
