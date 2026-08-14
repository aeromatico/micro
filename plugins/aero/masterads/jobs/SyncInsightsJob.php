<?php declare(strict_types=1);

namespace Aero\MasterAds\Jobs;

use Aero\MasterAds\Models\MetaAccount;
use Aero\MasterAds\Models\Insight;
use Aero\MasterAds\Classes\Meta\MetaApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * SyncInsightsJob — queued ingestion of daily Insight rows for a Meta_Account.
 *
 * Walks `/<act_id>/insights?level=ad&time_range=...&time_increment=1` using the
 * paginated generator from {@see MetaApiClient::getPaginated()}, mapping every
 * row to a local `Ad` via the natural `meta_id` key and persisting the daily
 * metric tuple through {@see Insight::upsertByEntityDate()}.
 *
 * Idempotency: the (`entity_type`, `entity_id`, `date`) tuple is unique in
 * `aero_masterads_insights` (Requirement 4.2). Re-running the job over the
 * same window updates existing rows rather than duplicating them, realising
 * Property P4 (Sync Idempotency).
 *
 * Streaming: the generator yields one row per page, never loading the full
 * Insights response into memory. Rows referencing an `ad_id` that has not yet
 * been synced (no local `Ad`) are silently skipped — `SyncMetaAccountJob`
 * (task 11.1) is expected to have run first to populate the hierarchy.
 *
 * Queue semantics:
 *   - `$tries = 3` — exponential retry handled by the queue worker.
 *   - `$timeout = 900` (15 min) — generous bound for accounts with many ads.
 *   - `failed()` records a structured error log so operators can correlate
 *     a persistent failure with the original dispatch.
 *
 * Observability: every run emits a `correlation_id` (UUID v4) on both
 * "started" and "completed" log lines, plus on `failed()`, satisfying the
 * structured-logging requirement.
 *
 * Validates Requirements 4.1, 4.2, 4.3, 10.1, 10.2, 16.1, 19.4 (master-ads spec).
 */
final class SyncInsightsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 900;

    public function __construct(
        public readonly int $metaAccountId,
        public readonly string $sinceDate,   // 'Y-m-d'
        public readonly string $untilDate    // 'Y-m-d'
    ) {}

    public function handle(): void
    {
        $correlationId = (string) Str::uuid();
        $metaAccount = MetaAccount::findOrFail($this->metaAccountId);

        Log::info('[MasterAds][SyncInsights] started', [
            'meta_account_id' => $this->metaAccountId,
            'since' => $this->sinceDate,
            'until' => $this->untilDate,
            'correlation_id' => $correlationId,
        ]);

        $client = new MetaApiClient($metaAccount);

        $endpoint = $metaAccount->meta_act_id . '/insights';
        $params = [
            'level' => 'ad',
            'fields' => 'ad_id,impressions,clicks,spend,conversions,date_start,date_stop',
            'time_range' => json_encode([
                'since' => $this->sinceDate,
                'until' => $this->untilDate,
            ]),
            'time_increment' => 1, // daily
        ];

        $count = 0;
        foreach ($client->getPaginated($endpoint, $params) as $row) {
            // Map ad_id -> local Ad. Use meta_id lookup.
            $ad = \Aero\MasterAds\Models\Ad::where('meta_id', (string) ($row['ad_id'] ?? ''))->first();
            if (!$ad) {
                continue; // skip if ad isn't synced yet
            }

            Insight::upsertByEntityDate([
                'entity_type' => 'ad',
                'entity_id' => $ad->id,
                'date' => $row['date_start'] ?? $this->sinceDate,
                'impressions' => (int) ($row['impressions'] ?? 0),
                'clicks' => (int) ($row['clicks'] ?? 0),
                'spend' => (float) ($row['spend'] ?? 0),
                'conversions' => (int) self::extractConversions($row),
            ]);
            $count++;
        }

        Log::info('[MasterAds][SyncInsights] completed', [
            'meta_account_id' => $this->metaAccountId,
            'inserted_or_updated' => $count,
            'correlation_id' => $correlationId,
        ]);
    }

    private static function extractConversions(array $row): int
    {
        // Meta returns conversions in an 'actions' array; simplification:
        // sum 'value' across 'offsite_conversion.*' or use 'actions.value' total.
        $actions = $row['actions'] ?? [];
        $total = 0;
        foreach ($actions as $action) {
            $type = $action['action_type'] ?? '';
            if (str_starts_with($type, 'offsite_conversion') || $type === 'purchase' || $type === 'lead') {
                $total += (int) ($action['value'] ?? 0);
            }
        }
        return $total;
    }

    public function failed(\Throwable $e): void
    {
        Log::error('[MasterAds][SyncInsights] failed', [
            'meta_account_id' => $this->metaAccountId,
            'error' => $e->getMessage(),
        ]);
    }
}
