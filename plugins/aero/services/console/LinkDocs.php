<?php namespace Aero\Services\Console;

use Aero\Docs\Models\Article;
use Aero\Docs\Models\Guide;
use Aero\Services\Models\Service;
use Illuminate\Console\Command;

/**
 * Vincula a un servicio los artículos del plugin «construido con» y las guías elegidas.
 * Lo ejecuta el CLI de docs tras generar; solo añade vínculos (nunca quita los que ya hay).
 */
class LinkDocs extends Command
{
    protected $signature = 'services:link-docs
        {service : ID o slug del servicio}
        {--plugin=* : Plugin (carpeta, ej. wpflash) cuyos artículos de plataforma se vinculan}
        {--guides= : Slugs de guías separados por coma}';

    protected $description = 'Vincula artículos de un plugin y guías al servicio.';

    public function handle(): int
    {
        $service = Service::where('id', $this->argument('service'))->orWhere('slug', $this->argument('service'))->first();
        if (!$service) {
            $this->error('Servicio no encontrado.');

            return self::FAILURE;
        }

        $articles = [];
        foreach ((array) $this->option('plugin') as $plugin) {
            $articles = array_merge($articles, Article::whereNull('tenant_id')->where('slug', 'like', $plugin . '-%')->pluck('id')->all());
        }
        if ($articles) {
            $service->articles()->syncWithoutDetaching(array_values(array_unique($articles)));
        }

        $slugs = array_filter(array_map('trim', explode(',', (string) $this->option('guides'))));
        $guides = $slugs ? Guide::whereNull('tenant_id')->whereIn('slug', $slugs)->pluck('id', 'slug')->all() : [];
        if ($guides) {
            $service->guides()->syncWithoutDetaching(array_values($guides));
        }

        $missing = array_diff($slugs, array_keys($guides));
        $this->line(json_encode([
            'articles_linked' => count($articles),
            'guides_linked'   => array_keys($guides),
            'guides_missing'  => array_values($missing),
        ], JSON_UNESCAPED_UNICODE));

        return $missing ? self::FAILURE : self::SUCCESS;
    }
}
