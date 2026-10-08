<?php namespace Aero\Chat\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;

/**
 * Pasa a ogg/opus una nota de voz grabada en el navegador (webm). Corre en el worker
 * porque exec() está deshabilitado en PHP-FPM; el controlador espera el resultado en
 * Cache con la clave chat:audio:{token} ('ok' | 'fail').
 */
class ConvertAudio implements ShouldQueue
{
    use Dispatchable, Queueable;

    public $tries = 1;

    public function __construct(public string $source, public string $target, public string $token)
    {
    }

    public function handle(): void
    {
        $out = [];
        exec('ffmpeg -y -loglevel error -i ' . escapeshellarg($this->source) . ' -vn -c:a libopus -b:a 32k ' . escapeshellarg($this->target) . ' 2>&1', $out, $code);
        Cache::put('chat:audio:' . $this->token, ($code === 0 && is_file($this->target) && filesize($this->target) > 0) ? 'ok' : 'fail', 120);
    }
}
