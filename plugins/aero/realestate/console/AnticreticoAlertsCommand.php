<?php namespace Aero\Realestate\Console;

use Aero\Realestate\Classes\Notifier;
use Illuminate\Console\Command;

/** realestate:anticretico-alerts — vencimientos (anticréticos y alquileres) y documentos pendientes o por vencer (vía Aero.Notify). */
class AnticreticoAlertsCommand extends Command
{
    protected $signature = 'realestate:anticretico-alerts';

    protected $description = 'Avisa por WhatsApp/correo los anticréticos y alquileres próximos a vencer y los documentos pendientes o por vencer.';

    public function handle(): int
    {
        $this->info('Vencimientos avisados: ' . Notifier::sendExpiryAlerts());
        $this->info('Contratos con documentos pendientes avisados: ' . Notifier::sendPendingDocuments());
        $this->info('Alquileres por vencer avisados: ' . Notifier::sendRentalExpiryAlerts());
        $this->info('Avisos del archivo documental: ' . Notifier::sendDocumentAlerts());

        return self::SUCCESS;
    }
}
