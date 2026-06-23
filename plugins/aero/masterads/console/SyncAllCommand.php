<?php declare(strict_types=1);

namespace Aero\MasterAds\Console;

use Aero\MasterAds\Jobs\SyncMetaAccountJob;
use Aero\MasterAds\Models\MetaAccount;
use Illuminate\Console\Command;

/**
 * SyncAllCommand — `masterads:sync-all`
 *
 * Iterates over every connected `MetaAccount` and dispatches a
 * {@see SyncMetaAccountJob} for each one onto the Redis queue. The job itself
 * is responsible for the actual Graph API traversal, idempotent upsert of the
 * Campaign → AdSet → Ad tree, and follow-up `SyncInsightsJob` enqueue (see
 * Tasks 11.1 and 11.2).
 *
 * Intended invocation:
 *   - Scheduled automatically every 4 hours via `Plugin::registerSchedule`
 *     (Task 14.4) with `withoutOverlapping()` and `onOneServer()` guards so a
 *     long-running sync wave is never duplicated across the cluster.
 *   - Manually for ad-hoc re-syncs, optionally narrowed by workspace or by a
 *     single ad account through the `--workspace` and `--account` filters.
 *
 * Filters:
 *   - `--workspace=<id>` limits the iteration to MetaAccounts of one
 *     workspace, useful for re-syncing a single tenant after support actions
 *     without touching everyone else's queue depth.
 *   - `--account=<id>`   targets a single MetaAccount, handy for replaying a
 *     failed sync after a token rotation.
 *
 * The two filters compose with AND semantics (both predicates applied to the
 * same query). When no rows match, the command emits a warning and exits
 * cleanly so the scheduler does not flag a missing-target invocation as a
 * failure.
 *
 * Output:
 *   - One `line()` per dispatched job carrying `#id`, `meta_act_id` and
 *     `workspace_id` so operators tailing `php artisan masterads:sync-all`
 *     can correlate against `aero_masterads_meta_accounts` rows directly.
 *   - A trailing `info()` summary with the dispatched count.
 *
 * Validates: Requirements 3.8, 10.3.
 */
class SyncAllCommand extends Command
{
    /**
     * @var string Artisan signature with optional narrowing filters.
     */
    protected $signature = 'masterads:sync-all
        {--workspace= : Limit to a specific workspace_id}
        {--account= : Limit to a specific meta_account_id}';

    /**
     * @var string Human-readable description shown by `php artisan list`.
     */
    protected $description = 'Dispatch a Meta sync job for every connected ad account.';

    /**
     * Build the MetaAccount query, apply optional filters, and dispatch one
     * {@see SyncMetaAccountJob} per matching row.
     *
     * @return int Process exit code (`self::SUCCESS` in all branches — a
     *             missing match is not an error condition for the scheduler).
     */
    public function handle(): int
    {
        $query = MetaAccount::query();

        if ($this->option('account')) {
            $query->where('id', (int) $this->option('account'));
        }
        if ($this->option('workspace')) {
            $query->where('workspace_id', (int) $this->option('workspace'));
        }

        $accounts = $query->get();
        if ($accounts->isEmpty()) {
            $this->warn('No MetaAccounts matched the filters.');
            return self::SUCCESS;
        }

        $count = 0;
        foreach ($accounts as $account) {
            SyncMetaAccountJob::dispatch($account);
            $this->line(sprintf(
                'Queued sync for account #%d (%s) — workspace %d',
                $account->id,
                $account->meta_act_id,
                $account->workspace_id
            ));
            $count++;
        }

        $this->info(sprintf('Dispatched %d sync job(s).', $count));
        return self::SUCCESS;
    }
}
