<?php namespace Aero\Social\Jobs;

use Aero\Social\Classes\MetaAds;
use Aero\Social\Classes\SocialException;
use Aero\Social\Models\AdAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Trae campañas y métricas de una cuenta publicitaria (cron horario). */
class SyncAdAccount implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 300;

    public function __construct(public int $adAccountId, public int $days = 3)
    {
    }

    public function handle(): void
    {
        $account = AdAccount::find($this->adAccountId);
        if (!$account || !$account->isActive() || $account->platform !== 'meta') {
            return;
        }

        try {
            MetaAds::sync($account, $this->days);
        } catch (SocialException $e) {
            // Ya quedó anotado en la cuenta (last_error / expired).
        }
    }
}
