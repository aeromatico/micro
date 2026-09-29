<?php namespace Aero\Docs\Console;

use Aero\Docs\Models\Article;
use Illuminate\Console\Command;

/**
 * Por plugin: cuántos artículos de plataforma tiene y la versión documentada
 * MÍNIMA (JSON). Se cuenta como documentada la edición pendiente de aprobar,
 * para que docs-sync no repita el trabajo mientras espera revisión. Si algún
 * artículo va por detrás, el plugin sigue figurando como desactualizado.
 * El plugin se deduce del prefijo del slug.
 */
class VersionsCommand extends Command
{
    protected $signature = 'docs:versions';

    protected $description = 'Artículos y versión documentada mínima por plugin, en JSON.';

    public function handle(): int
    {
        $by = [];
        foreach (Article::whereNull('tenant_id')->get(['slug', 'plugin_version', 'pending_plugin_version']) as $a) {
            $plugin = strstr($a->slug, '-', true) ?: $a->slug;
            $versions = array_filter([$a->plugin_version, $a->pending_plugin_version]);
            $effective = $versions ? array_reduce($versions, fn ($c, $v) => $c === null || version_compare($v, $c, '>') ? $v : $c) : null;
            $by[$plugin][] = $effective;
        }

        $out = [];
        foreach ($by as $plugin => $list) {
            $known = array_values(array_filter($list));
            usort($known, 'version_compare');
            $out[$plugin] = ['n' => count($known), 'min' => $known[0] ?? null];
        }
        $this->line(json_encode($out, JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
