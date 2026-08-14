<?php declare(strict_types=1);

namespace Aero\MasterAds\Console;

use Aero\MasterAds\Jobs\RunAiAnalysisJob;
use Aero\MasterAds\Models\Campaign;
use Aero\MasterAds\Models\Workspace;
use Illuminate\Console\Command;

/**
 * AnalyzeCommand — `masterads:analyze [--auto]`
 *
 * Thin CLI surface in front of {@see RunAiAnalysisJob}. The command exists
 * so operators (and the OctoberCMS scheduler — see Task 14.4) can trigger
 * AI-driven analyses without going through the backend UI.
 *
 * Two mutually-exclusive modes:
 *
 *   1) Manual mode — `--target-type` and `--target-id` are both required.
 *      The command validates the target-type domain (`campaign|adset|ad`),
 *      coerces `target-id` to a positive integer, and dispatches a single
 *      `RunAiAnalysisJob` carrying the resolved `lookback_days` option.
 *
 *   2) Auto mode — `--auto` flag (target-* options are ignored).
 *      Iterates over every {@see Workspace} whose `settings.auto_analyze`
 *      JSON flag is truthy, then for each such workspace dispatches one
 *      `RunAiAnalysisJob` per ACTIVE Campaign attached to any of its
 *      connected MetaAccounts. This is the entry point scheduled to run
 *      daily at 06:00 (Requirement 10.6).
 *
 * Filter semantics (auto mode):
 *   - `settings.auto_analyze === true` (strict) — defensive against
 *     truthy-but-not-true values that operators might set by mistake
 *     (e.g. the string `"false"`).
 *   - Campaign filter: `status = ACTIVE` only. Paused / archived
 *     campaigns are intentionally excluded; analyzing inactive entities
 *     would waste AI quota with no actionable outcome.
 *
 * Exit codes:
 *   - `SUCCESS` (0): jobs dispatched (or no candidates found in auto mode
 *     — a missing target is not an error condition for the scheduler).
 *   - `FAILURE` (1): missing/invalid manual-mode arguments.
 *
 * Validates: Requirements 6.11, 10.3.
 */
class AnalyzeCommand extends Command
{
    /**
     * @var string Artisan signature. Manual options become required only
     *             when `--auto` is absent; that contract is enforced at
     *             runtime inside {@see handle()} because Laravel's option
     *             parser does not support cross-option conditional rules.
     */
    protected $signature = 'masterads:analyze
        {--target-type= : campaign|adset|ad (required unless --auto)}
        {--target-id= : Primary key of the target (required unless --auto)}
        {--lookback-days=14 : Lookback window for metrics aggregation}
        {--auto : Iterate workspaces marked as auto-analyze}';

    /**
     * @var string Human-readable description shown by `php artisan list`.
     */
    protected $description = 'Dispatch RunAiAnalysisJob for one target or every auto-analyze workspace.';

    /**
     * Entry point — routes to the appropriate mode handler.
     *
     * @return int Process exit code.
     */
    public function handle(): int
    {
        if ($this->option('auto')) {
            return $this->runAuto();
        }

        $type = (string) $this->option('target-type');
        $id   = (int) $this->option('target-id');

        if (!in_array($type, ['campaign', 'adset', 'ad'], true) || $id <= 0) {
            $this->error('--target-type (campaign|adset|ad) and --target-id are required when --auto is not used.');
            return self::FAILURE;
        }

        RunAiAnalysisJob::dispatch($type, $id, [
            'lookback_days' => (int) $this->option('lookback-days'),
        ]);
        $this->info("Dispatched analysis for {$type} #{$id}");
        return self::SUCCESS;
    }

    /**
     * Auto-analyze mode: iterate workspaces flagged as auto-analyze and
     * dispatch one job per ACTIVE Campaign connected to each workspace.
     *
     * MVP heuristic — every ACTIVE Campaign of every connected MetaAccount
     * is a candidate. Future revisions may refine this with a per-campaign
     * "stale analysis" predicate to avoid re-analyzing campaigns that were
     * just analyzed manually.
     *
     * @return int `SUCCESS` regardless of whether candidates were found,
     *             so the scheduler does not flag empty waves as failures.
     */
    private function runAuto(): int
    {
        // Filter at the collection level: the `settings` JSON column is
        // jsonable on Workspace, so a SQL-side predicate would require
        // database-specific JSON path syntax. The set of workspaces is
        // small enough (tenant count, not record count) to filter in PHP.
        $workspaces = Workspace::all()
            ->filter(fn($w) => ($w->settings['auto_analyze'] ?? false) === true);

        if ($workspaces->isEmpty()) {
            $this->warn('No workspaces have settings.auto_analyze enabled.');
            return self::SUCCESS;
        }

        $count = 0;
        foreach ($workspaces as $ws) {
            $campaigns = Campaign::query()
                ->whereIn('meta_account_id', $ws->meta_accounts()->pluck('id'))
                ->where('status', 'ACTIVE')
                ->get();

            foreach ($campaigns as $campaign) {
                RunAiAnalysisJob::dispatch('campaign', $campaign->id, [
                    'lookback_days' => (int) $this->option('lookback-days'),
                    'triggered_by'  => null,
                    'auto'          => true,
                ]);
                $this->line("Queued auto-analysis for campaign #{$campaign->id} (workspace {$ws->id})");
                $count++;
            }
        }

        $this->info("Dispatched {$count} auto-analysis job(s).");
        return self::SUCCESS;
    }
}
