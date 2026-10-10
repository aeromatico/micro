<?php namespace Aero\Office\Console;

use Aero\Office\Classes\Notifier;
use Illuminate\Console\Command;

/** office:remind — recordatorios de citas por WhatsApp/correo (vía Aero.Notify). */
class RemindCommand extends Command
{
    protected $signature = 'office:remind';

    protected $description = 'Envía los recordatorios de citas próximas según las horas configuradas por negocio.';

    public function handle(): int
    {
        $this->info('Recordatorios enviados: ' . Notifier::sendReminders());

        return self::SUCCESS;
    }
}
