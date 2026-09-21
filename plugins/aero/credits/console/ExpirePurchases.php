<?php namespace Aero\Credits\Console;

use Aero\Credits\Classes\Recharges;
use Illuminate\Console\Command;

class ExpirePurchases extends Command
{
    protected $signature = 'credits:expire-purchases';

    protected $description = 'Vence las recargas cuyo QR no se pagó a tiempo (y anula el QR).';

    public function handle(): int
    {
        $this->info(Recharges::expirePending() . ' recarga(s) vencidas.');

        return self::SUCCESS;
    }
}
