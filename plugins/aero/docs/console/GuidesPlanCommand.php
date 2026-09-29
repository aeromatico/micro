<?php namespace Aero\Docs\Console;

use Aero\Docs\Classes\GuideSources;
use Aero\Docs\Models\Guide;
use Illuminate\Console\Command;

/**
 * Plan determinista (sin IA) de guías: qué formularios no tienen guía o la
 * tienen desactualizada. Lo consume el watcher de docs-sync; imprime JSON.
 */
class GuidesPlanCommand extends Command
{
    protected $signature = 'docs:guides {plugin? : Plugin (ej. pay); vacío = todos}';

    protected $description = 'Lista los formularios que necesitan generar o refrescar su guía interactiva (JSON).';

    public function handle(): int
    {
        $plugins = $this->argument('plugin') ? [$this->argument('plugin')] : GuideSources::plugins();
        $rows = [];

        foreach ($plugins as $plugin) {
            foreach (GuideSources::forPlugin($plugin) as $form) {
                $guide = Guide::whereNull('tenant_id')->where('slug', $form['slug'])->first();
                $form['db_hash'] = $guide?->source_hash;
                $form['state']   = !$guide ? 'missing' : ($guide->source_hash !== $form['source_hash'] ? 'stale' : 'current');
                $form['pending'] = (bool) $guide?->pending_html;
                $rows[] = $form;
            }
        }

        $this->line(json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
