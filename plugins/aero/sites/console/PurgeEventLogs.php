<?php namespace Aero\Sites\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Borra del registro de eventos del sistema (system_event_logs) lo que supera
 * la retención. Va en lotes por id para no bloquear la tabla.
 */
class PurgeEventLogs extends Command
{
    protected $signature = 'aero.sites:purge-event-logs {--days=30} {--batch=2000} {--max=0 : máximo de filas a borrar (0 = sin tope)}';

    protected $description = 'Borra el registro de eventos del sistema con más de N días (30 por defecto).';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $batch = max(100, (int) $this->option('batch'));
        $max = (int) $this->option('max');
        $cutoff = now()->subDays($days);
        $total = 0;

        do {
            $ids = DB::table('system_event_logs')->where('created_at', '<', $cutoff)->orderBy('id')->limit($batch)->pluck('id');
            if ($ids->isEmpty()) {
                break;
            }
            $total += DB::table('system_event_logs')->whereIn('id', $ids)->delete();
            usleep(50000);
        } while (!$max || $total < $max);

        $this->info("{$total} registro(s) de eventos con más de {$days} días borrado(s).");

        return self::SUCCESS;
    }
}
