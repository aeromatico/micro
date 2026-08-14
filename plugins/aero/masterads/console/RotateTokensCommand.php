<?php declare(strict_types=1);

namespace Aero\MasterAds\Console;

use Aero\MasterAds\Classes\Meta\MetaTokenRefresher;
use Aero\MasterAds\Models\MetaAccount;
use Illuminate\Console\Command;
use Throwable;

/**
 * masterads:rotate-tokens — Refreshes Meta access tokens that are within
 * `--days` (default 7) of expiration. Intended for daily 03:00 scheduling.
 *
 * Validates: Requirements 2.7, 10.3, 15.6
 */
class RotateTokensCommand extends Command
{
    protected $signature = 'masterads:rotate-tokens
        {--days=7 : Refresh tokens expiring within this many days}';

    protected $description = 'Refresh Meta access tokens close to expiration.';

    public function handle(MetaTokenRefresher $refresher): int
    {
        $days = max(1, (int) $this->option('days'));
        $accounts = MetaAccount::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays($days))
            ->get();

        if ($accounts->isEmpty()) {
            $this->info("No MetaAccounts have a token expiring within {$days} days.");
            return self::SUCCESS;
        }

        $rotated = 0;
        $failed = 0;
        foreach ($accounts as $account) {
            try {
                $refresher->refresh($account);
                $this->line("Rotated token for account #{$account->id} ({$account->meta_act_id})");
                $rotated++;
            } catch (Throwable $e) {
                $this->error("Failed for #{$account->id} ({$account->meta_act_id}): " . $e->getMessage());
                $failed++;
            }
        }

        $this->info("Rotated {$rotated} token(s), {$failed} failure(s).");
        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
