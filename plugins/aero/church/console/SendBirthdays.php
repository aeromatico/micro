<?php namespace Aero\Church\Console;

use Illuminate\Console\Command;

class SendBirthdays extends Command
{
    protected $name = 'church:birthdays';
    protected $description = 'Envía la felicitación de cumpleaños por WhatsApp (solo iglesias que la activaron y miembros con consentimiento).';

    public function handle(): int
    {
        $this->info('Felicitaciones enviadas: ' . app(\Aero\Church\Classes\Birthdays::class)->run());

        return self::SUCCESS;
    }
}
