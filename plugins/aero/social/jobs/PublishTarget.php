<?php namespace Aero\Social\Jobs;

use Aero\Social\Classes\Publisher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Publica un destino en su red. Un solo intento: reintentar a ciegas podría
 * duplicar el post. El reintento es manual (botón) o vía sweep().
 */
class PublishTarget implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 150;

    public function __construct(public int $targetId)
    {
    }

    public function handle(): void
    {
        Publisher::run($this->targetId);
    }
}
