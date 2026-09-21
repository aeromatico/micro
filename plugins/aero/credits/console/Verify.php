<?php namespace Aero\Credits\Console;

use Aero\Credits\Classes\Credits;
use Illuminate\Console\Command;

class Verify extends Command
{
    protected $signature = 'credits:verify';

    protected $description = 'Audita todas las invariantes de la contabilidad de créditos (saldos, cadena de saldos, acumulado, intercambios, reembolsos).';

    public function handle(): int
    {
        $problems = Credits::verify();

        if (!$problems) {
            $this->info('Contabilidad perfecta: todas las invariantes se cumplen.');
            return self::SUCCESS;
        }

        foreach ($problems as $p) {
            $this->error($p);
        }

        \Log::critical('Aero.Credits: la auditoría de contabilidad encontró problemas: ' . implode(' | ', $problems));

        return self::FAILURE;
    }
}
