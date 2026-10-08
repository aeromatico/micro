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

        return array_merge(static::serviceItems($documented, $skipDocs), $items);
    }

    /**
     * Un servicio (Aero.Services) se identifica con su plugin «Construido con el plugin»
     * (Plataforma → Plugins ligados). Por cada uno hay hasta tres cosas por crear: la documentación
     * del plugin, sus guías interactivas y la propia página del servicio.
     *
     * @return array<int, array<string, mixed>>
     */
    protected static function serviceItems(array $documented, array $skipDocs): array
    {
        if (!class_exists(\Aero\Services\Models\Service::class)) {
            return [];
        }

        $out = [];
        foreach (\Aero\Services\Models\Service::orderBy('id')->get() as $svc) {
            $plugins = [];
            foreach ((array) $svc->plugin_links as $link) {
                if (($link['relation'] ?? null) !== 'built_with' || empty($link['plugin'])) {
                    continue;
                }
                $dir = strtolower(substr(strrchr('.' . $link['plugin'], '.'), 1));
                if (!is_dir(plugins_path('aero/' . $dir)) || isset($plugins[$dir])) {
                    continue;
                }

                $current = static::currentVersion($dir);
                $n = $documented[$dir]['n'] ?? 0;
                $min = $documented[$dir]['min'] ?? null;
                $docState = $n === 0 ? 'undocumented' : (($min && $current && version_compare($min, $current, '<')) ? 'outdated' : 'current');
                if (isset($skipDocs[$dir]) && $current && version_compare($skipDocs[$dir], $current, '>=')) {
                    $docState = 'current';
                }

                $linked = $svc->guides()->pluck('slug')->all();
                $forms = [];
                foreach (GuideSources::forPlugin($dir) as $form) {
                    $guide = Guide::whereNull('tenant_id')->where('slug', $form['slug'])->first();
                    $forms[] = [
                        'slug' => $form['slug'], 'controller' => $form['controller'], 'name' => $form['name'],
                        'state' => !$guide ? 'missing' : ($guide->source_hash !== $form['source_hash'] ? 'stale' : 'current'),
                        'published' => $guide?->status === 'published',
                        'linked' => in_array($form['slug'], $linked, true),
                    ] + $form;
                }

                $plugins[$dir] = [
                    'plugin' => $dir, 'code' => $link['plugin'], 'current' => $current, 'documented' => $min,
                    'doc_state' => $docState, 'forms' => $forms,
                ];
            }
            if (!$plugins) {
                continue;
            }

            $offer = filled($svc->code) ? 'ok' : 'missing';
            $todoDocs = array_keys(array_filter($plugins, fn ($p) => $p['doc_state'] !== 'current'));
            $todoForms = 0;
            foreach ($plugins as $p) {
                $todoForms += count(array_filter($p['forms'], fn ($f) => $f['state'] !== 'current'));
            }
            if ($offer === 'ok' && !$todoDocs && !$todoForms) {
                continue;
            }

            $bits = [];
            $bits[] = $offer === 'missing' ? 'página vacía' : 'página completa';
            $bits[] = $todoDocs ? 'doc: ' . implode(', ', array_map(fn ($d) => $d . ' ' . ($plugins[$d]['doc_state'] === 'undocumented' ? 'sin documentar' : 'desactualizada'), $todoDocs)) : 'doc al día';
            $bits[] = 'guías: ' . $todoForms . ' formulario(s) por generar';

            $first = reset($plugins);
            $out[] = [
                'id' => 'service:' . $svc->id, 'kind' => 'service', 'plugin' => $first['plugin'], 'slug' => $svc->slug,
                'title' => $svc->name, 'state' => $offer === 'missing' ? 'incomplete' : 'partial',
                'reason' => implode(' · ', $bits), 'service_id' => $svc->id, 'public' => (bool) $svc->is_active,
                'offer_state' => $offer, 'plugins' => array_values($plugins),
            ];
        }

        return $out;
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
