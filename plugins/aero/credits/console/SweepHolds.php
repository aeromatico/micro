<?php namespace Aero\Credits\Console;

use Aero\Credits\Classes\Credits;
use Illuminate\Console\Command;

class SweepHolds extends Command
{
    protected $signature = 'credits:sweep-holds';

    protected $description = 'Reembolsa los cobros pendientes cuya acción nunca terminó (hold vencido).';

    public function handle(): int
    {
        $n = Credits::sweepHolds();
        $this->info("{$n} hold(s) vencidos reembolsados.");

        return self::SUCCESS;
    }
}
