<?php namespace Aero\Church\Console;

use Illuminate\Console\Command;

class RemindEvents extends Command
{
    protected $name = 'church:remind-events';
    protected $description = 'Envía por WhatsApp el recordatorio de los eventos próximos (solo a quien dio su consentimiento).';

    public function handle(): int
    {
        $this->info('Recordatorios enviados: ' . app(\Aero\Church\Classes\Reminders::class)->run());

        return self::SUCCESS;
    }
}
