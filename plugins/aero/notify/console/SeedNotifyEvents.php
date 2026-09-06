<?php namespace Aero\Notify\Console;

use Aero\Notify\Classes\EventSeeder;
use Illuminate\Console\Command;

class SeedNotifyEvents extends Command
{
    protected $name = 'notify:seed-events';

    protected $description = 'Siembra o actualiza el catálogo de eventos notificables.';

    public function handle(): int
    {
        $withRules = !$this->option('no-rules');

        $result = (new EventSeeder)->run($withRules);

        $this->info(sprintf(
            'Eventos: %d creados, %d actualizados. Reglas globales creadas: %d.',
            $result['created'],
            $result['updated'],
            $result['rules']
        ));

        return 0;
    }

    protected function getOptions(): array
    {
        return [
            ['no-rules', null, \Symfony\Component\Console\Input\InputOption::VALUE_NONE,
                'Solo sembrar los eventos, sin crear las reglas globales por defecto.'],
        ];
    }
}
