<?php namespace Aero\Notify\Console;

use Aero\Notify\Classes\Notify;
use Aero\Notify\Models\Delivery;
use Illuminate\Console\Command;

/** Avisa a los superadmins cuando un canal acumula fallos (evento notify.delivery.failed_burst). */
class WatchFailures extends Command
{
    protected $signature = 'notify:watch {--minutes=15} {--threshold=5}';

    protected $description = 'Detecta ráfagas de entregas fallidas por canal.';

    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');

        $bursts = Delivery::where('status', 'failed')
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->selectRaw('channel, count(*) as total')
            ->groupBy('channel')
            ->having('total', '>=', (int) $this->option('threshold'))
            ->get();

        foreach ($bursts as $row) {
            Notify::fire('notify.delivery.failed_burst', [
                'channel'      => $row->channel,
                'failed_count' => $row->total,
                'window'       => "{$minutes} min",
            ], ['dedup_key' => 'burst:' . $row->channel . ':' . now()->format('YmdH'), 'sync' => true]);

            $this->warn("{$row->channel}: {$row->total} fallos");
        }

        return 0;
    }
}
