<?php namespace Aero\Realestate\Console;

use Aero\Realestate\Classes\Notifier;
use Illuminate\Console\Command;

/** realestate:remind — recordatorios de visitas, seguimientos vencidos y alertas del portal (vía Aero.Notify). */
class RemindCommand extends Command
{
    protected $signature = 'realestate:remind';

    protected $description = 'Envía los recordatorios de visitas, los seguimientos vencidos y las alertas del portal.';

    public function handle(): int
    {
        $this->info('Recordatorios de visita: ' . Notifier::sendVisitReminders());
        $this->info('Seguimientos avisados: ' . Notifier::sendFollowUps());
        $this->info('Alertas de búsquedas guardadas: ' . Notifier::sendSearchAlerts());
        $this->info('Alertas de cambios en favoritos: ' . Notifier::sendChangeAlerts());

        return self::SUCCESS;
    }
}
