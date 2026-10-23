<?php namespace Aero\Church\Console;

use Illuminate\Console\Command;

class SyncContributions extends Command
{
    protected $name = 'church:sync-contributions';
    protected $description = 'Concilia los aportes por QR cuyo pago ya se confirmó en Pagos.';

    public function handle(): int
    {
        $this->info('Aportes conciliados: ' . app(\Aero\Church\Classes\ContributionService::class)->syncPayments());

        return self::SUCCESS;
    }
}
