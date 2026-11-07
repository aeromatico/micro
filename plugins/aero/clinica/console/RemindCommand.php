<?php namespace Aero\Clinica\Console;

use Aero\Clinica\Classes\Reminders;
use Illuminate\Console\Command;

class RemindCommand extends Command
{
    protected $signature = 'clinica:remind';

    protected $description = 'Avisa a los pacientes de sus vacunas próximas (solo negocios que lo activaron)';

    public function handle(): int
    {
        $this->info('Avisos de vacuna enviados: ' . Reminders::vaccines());

        return 0;
    }
}
