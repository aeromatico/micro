<?php namespace Aero\Gym\Console;

use Aero\Gym\Classes\SessionGenerator;
use Illuminate\Console\Command;

/** gym:generate-sessions — convierte los horarios semanales en sesiones concretas. */
class GenerateSessionsCommand extends Command
{
    protected $signature = 'gym:generate-sessions {--days=14 : Días hacia adelante}';

    protected $description = 'Genera las clases (sesiones) de los próximos días a partir de los horarios semanales.';

    public function handle(SessionGenerator $generator): int
    {
        $n = $generator->generate((int) $this->option('days'));
        $this->info("Sesiones creadas: {$n}.");

        return self::SUCCESS;
    }
}
