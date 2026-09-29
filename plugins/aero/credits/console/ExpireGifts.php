<?php namespace Aero\Credits\Console;

use Aero\Credits\Classes\Gifts;
use Illuminate\Console\Command;

class ExpireGifts extends Command
{
    protected $signature = 'credits:expire-gifts';

    protected $description = 'Vence los regalos de suscripción cuyo QR no se pagó a tiempo.';

    public function handle(): int
    {
        $this->info(Gifts::expirePending() . ' regalo(s) vencidos.');

        return self::SUCCESS;
    }
}
