<?php namespace Aero\Sites\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retención de 30 días de los registros técnicos de los plugins (solo tablas
 * que son bitácoras: nunca datos de negocio). Lista cerrada a propósito.
 */
class PurgeTechnicalLogs extends Command
{
    protected $signature = 'aero.sites:purge-logs {--days=30} {--batch=2000}';

    protected $description = 'Borra los registros técnicos (conectores, chatbots, entregas de notificaciones) con más de N días.';

    /** tabla => columna de fecha */
    protected array $tables = [
        'aero_connector_logs'     => 'created_at',
        'aero_chatbots_logs'      => 'created_at',
        'aero_notify_deliveries'  => 'created_at',
    ];

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $batch = max(100, (int) $this->option('batch'));
        $cutoff = now()->subDays($days);

        foreach ($this->tables as $table => $column) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            $total = 0;
            do {
                $ids = DB::table($table)->where($column, '<', $cutoff)->orderBy('id')->limit($batch)->pluck('id');
                if ($ids->isEmpty()) {
                    break;
                }
                $total += DB::table($table)->whereIn('id', $ids)->delete();
            } while (true);

            $this->info("{$table}: {$total} fila(s) con más de {$days} días borrada(s).");
        }

        return self::SUCCESS;
    }
}
