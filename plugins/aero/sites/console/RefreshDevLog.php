<?php namespace Aero\Sites\Console;

use Aero\Sites\Classes\DevLog;
use Illuminate\Console\Command;

/**
 * Regenera storage/app/devlog.json (bitácora pública de /dev) desde git.
 * Correr como `www`, p. ej. por cron cada 30 min.
 */
class RefreshDevLog extends Command
{
    protected $name = 'aero.sites:refresh-devlog';

    protected $description = 'Regenera el snapshot de la bitácora pública /dev desde git.';

    public function handle()
    {
        $data = DevLog::refresh();

        $this->info(count($data['log']) . ' entradas, ' . $data['stats']['commits'] . ' commits.');
    }
}
