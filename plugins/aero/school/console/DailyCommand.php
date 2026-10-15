<?php namespace Aero\School\Console;

use Aero\School\Classes\Billing;
use Aero\School\Classes\Collections;
use Illuminate\Console\Command;

/** school:daily — cobros por QR recibidos y recordatorios de cobranza por WhatsApp. */
class DailyCommand extends Command
{
    protected $signature = 'school:daily';

    protected $description = 'Sincroniza cobros por QR y envía recordatorios de cuotas por WhatsApp.';

    public function handle(Billing $billing, Collections $collections): int
    {
        $paid = $billing->syncQrPayments();
        $sent = $collections->run();
        $this->info("Cobros por QR registrados: {$paid}. Recordatorios enviados: {$sent}.");

        return self::SUCCESS;
    }
}
