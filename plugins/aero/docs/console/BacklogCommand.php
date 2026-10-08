<?php namespace Aero\Docs\Console;

use Aero\Docs\Classes\Backlog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Backlog de documentación y guías (JSON), sin IA. Con --cache guarda los
 * totales para el contador del menú del backend (lo ejecuta el cron).
 */
class BacklogCommand extends Command
{
    protected $signature = 'docs:backlog {--all : Incluir guías de plugins aún sin documentar} {--cache : Guardar los totales para el menú}';

    protected $description = 'Qué documentación y guías se podrían generar ahora (JSON, sin IA).';

    public function handle(): int
    {
        $items = Backlog::compute((bool) $this->option('all'));

        if ($this->option('cache')) {
            Cache::forever('aero.docs.backlog', [
                'total' => count($items),
                'docs'  => count(array_filter($items, fn ($i) => in_array($i['kind'], ['doc', 'bootstrap'], true))),
                'services' => count(array_filter($items, fn ($i) => $i['kind'] === 'service')),
                'guides' => count(array_filter($items, fn ($i) => $i['kind'] === 'guide')),
                'at'    => now()->toDateTimeString(),
            ]);
        }

        $this->line(json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
