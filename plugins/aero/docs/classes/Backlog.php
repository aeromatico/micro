<?php namespace Aero\Docs\Classes;

use Aero\Docs\Models\Article;
use Aero\Docs\Models\Guide;

/**
 * Lo que docs-sync PODRÍA generar ahora, sin IA y sin gastar nada: documentación
 * desactualizada, plugins sin documentar y guías faltantes o desactualizadas.
 * Nada se genera solo: una persona elige qué incorporar con el CLI
 * (.claude/agents/docs-sync/docs-cli.py) y solo entonces se llama a Claude.
 *
 * Omitidos (no todo merece guía o actualización):
 *   content/guides/exclude.json  → ["plugin/controlador", …]  (formularios sin guía)
 *   content/docs-skip.json       → {"plugin": "versión"}       (doc omitida hasta una versión mayor)
 */
class Backlog
{
    /** @return array<int, array<string, mixed>> */
    public static function compute(bool $allPlugins = false): array
    {
        $documented = static::documented();
        $skipDocs = static::skipDocs();
        $items = [];

        foreach (glob(plugins_path('aero/*'), GLOB_ONLYDIR) as $dir) {
            $plugin = basename($dir);
            $current = static::currentVersion($plugin);
            if (!$current) {
                continue;
            }
            $n = $documented[$plugin]['n'] ?? 0;
            $min = $documented[$plugin]['min'] ?? null;

            $skipped = isset($skipDocs[$plugin]) && version_compare($skipDocs[$plugin], $current, '>=');

            if ($n === 0 && !$skipped) {
                $items[] = [
                    'id' => 'bootstrap:' . $plugin, 'kind' => 'bootstrap', 'plugin' => $plugin, 'slug' => $plugin,
                    'title' => 'Documentar ' . $plugin . ' por primera vez', 'state' => 'undocumented',
                    'reason' => 'sin artículos de documentación', 'current' => $current, 'documented' => null,
                ];
            } elseif ($n > 0 && $min && version_compare($min, $current, '<') && !$skipped) {
                $count = count(array_filter(static::versions($plugin), fn ($v) => version_compare($v, $min, '>')));
                $items[] = [
                    'id' => 'doc:' . $plugin, 'kind' => 'doc', 'plugin' => $plugin, 'slug' => $plugin,
                    'title' => 'Actualizar la documentación de ' . $plugin, 'state' => 'outdated',
                    'reason' => "documentada {$min} → actual {$current} ({$count} versión(es))",
                    'current' => $current, 'documented' => $min,
                ];
            }

            if ($n > 0 || $allPlugins) {
                foreach (GuideSources::forPlugin($plugin) as $form) {
                    $guide = Guide::whereNull('tenant_id')->where('slug', $form['slug'])->first();
                    $state = !$guide ? 'missing' : ($guide->source_hash !== $form['source_hash'] ? 'stale' : 'current');
                    if ($state === 'current') {
                        continue;
                    }
                    $items[] = $form + [
                        'id' => 'guide:' . $form['slug'], 'kind' => 'guide', 'title' => $form['name'],
                        'state' => $state,
                        'reason' => $state === 'missing' ? 'sin guía' : 'el formulario cambió desde la última guía',
                        'pending' => (bool) $guide?->pending_html,
                    ];
                }
            }
        }

        return $items;
    }

    /** @return array<string, array{n: int, min: ?string}> */
    public static function documented(): array
    {
        $by = [];
        foreach (Article::whereNull('tenant_id')->get(['slug', 'plugin_version', 'pending_plugin_version']) as $a) {
            $plugin = strstr($a->slug, '-', true) ?: $a->slug;
            $versions = array_filter([$a->plugin_version, $a->pending_plugin_version]);
            $by[$plugin][] = $versions
                ? array_reduce($versions, fn ($c, $v) => $c === null || version_compare($v, $c, '>') ? $v : $c)
                : null;
        }

        $out = [];
        foreach ($by as $plugin => $list) {
            $known = array_values(array_filter($list));
            usort($known, 'version_compare');
            $out[$plugin] = ['n' => count($known), 'min' => $known[0] ?? null];
        }

        return $out;
    }

    /** @return array<int, string> */
    public static function versions(string $plugin): array
    {
        $file = plugins_path('aero/' . $plugin . '/updates/version.yaml');
        if (!is_file($file)) {
            return [];
        }
        preg_match_all('/^\s*([0-9]+\.[0-9]+\.[0-9]+):/m', (string) file_get_contents($file), $m);
        $v = array_values(array_unique($m[1] ?? []));
        usort($v, 'version_compare');

        return $v;
    }

    public static function currentVersion(string $plugin): ?string
    {
        $v = static::versions($plugin);

        return $v ? end($v) : null;
    }

    /** @return array<string, string> */
    public static function skipDocs(): array
    {
        $file = plugins_path('aero/docs/content/docs-skip.json');
        $list = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];

        return is_array($list) ? $list : [];
    }
}
