<?php namespace Aero\Tracking\Console;

use Aero\Tracking\Models\Position;
use Illuminate\Console\Command;

class PrunePositions extends Command
{
    protected $name = 'aero.tracking:prune';

    protected $description = 'Elimina posiciones más antiguas que N días (por defecto 30).';

    public function handle(): int
    {
        $days = max(1, (int) ($this->option('days') ?: 30));

        $total = 0;
        do {
            $deleted = Position::where('recorded_at', '<', now()->subDays($days))->limit(5000)->delete();
            $total += $deleted;
        } while ($deleted > 0);

        $this->info("Posiciones eliminadas: {$total}");

        return self::SUCCESS;
    }

    protected function getOptions(): array
    {
        return [['days', null, \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL, 'Días de retención', 30]];
    }
}
