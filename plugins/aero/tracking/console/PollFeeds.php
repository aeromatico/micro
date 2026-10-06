<?php namespace Aero\Tracking\Console;

use Aero\Tracking\Classes\Feeds\FeedService;
use Illuminate\Console\Command;

class PollFeeds extends Command
{
    protected $name = 'aero.tracking:feeds';

    protected $description = 'Reengancha el seguimiento de feeds activos cuya cadena de polling se interrumpió.';

    public function handle(): int
    {
        $this->info('Feeds reenganchados: ' . FeedService::sweep());

        return self::SUCCESS;
    }
}
