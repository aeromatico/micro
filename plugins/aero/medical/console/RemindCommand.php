<?php namespace Aero\Medical\Console;

use Aero\Medical\Classes\Reminders;
use Illuminate\Console\Command;

class RemindCommand extends Command
{
    protected $signature = 'medical:remind';

    protected $description = 'Avisa a clientes y tutores de las vacunas próximas (solo negocios que lo activaron)';

    public function handle(): int
    {
        $this->info('Avisos de vacuna enviados: ' . Reminders::vaccines());

        return 0;
    }
}
