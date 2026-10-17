<?php namespace Aero\Sites\Classes;

use Cache;
use Lang;
use System\Classes\PluginManager;
use System\Classes\VersionManager;

/**
 * Datos de la página pública /dev: plugins con su versión aplicada y
 * bitácora de mejoras (commits `feat`/`fix`/`perf` del repo). Cacheado
 * porque `git log` sobre ~750 commits no debe correr en cada visita.
 */
class DevLog
{
    const CACHE_KEY = 'aero.sites.devlog';
    const TTL = 3600;

    const TYPES = [
        'feat' => 'Novedad',
        'fix' => 'Corrección',
        'perf' => 'Rendimiento',
    ];

    public static function data(): array
    {
        return Cache::remember(self::CACHE_KEY, self::TTL, function () {
            return [
                'plugins' => self::plugins(),
                'log' => self::log(),
                'stats' => self::stats(),
            ];
        });
    }

    public static function plugins(): array
    {
        $versions = VersionManager::instance();
        $out = [];

        foreach (PluginManager::instance()->getPlugins() as $code => $plugin) {
            if (stripos($code, 'Aero.') !== 0) {
                continue;
            }
            $details = $plugin->pluginDetails();
            $name = (string) ($details['name'] ?? $code);
            if (str_contains($name, '::')) {
                $name = Lang::get($name);
            }
            $description = (string) ($details['description'] ?? '');
            if (str_contains($description, '::')) {
                $description = Lang::get($description);
                $description = str_contains($description, '::') ? '' : $description;
            }
            $slug = strtolower(substr($code, 5));
            $out[] = [
                'slug' => $slug,
                'name' => str_contains($name, '::') ? ucfirst($slug) : $name,
                'version' => $versions->getLatestVersion($code) ?: '1.0.0',
                'description' => $description,
            ];
        }

        usort($out, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return $out;
    }

    public static function log(int $limit = 400): array
    {
        $raw = self::git(['log', '--no-merges', '--date=short', '--format=%h%x09%ad%x09%s', '-n', '3000']);
        $entries = [];

        foreach (explode("\n", trim($raw)) as $line) {
            $parts = explode("\t", $line, 3);
            if (count($parts) < 3) {
                continue;
            }
            [$hash, $date, $subject] = $parts;
            if (!preg_match('/^(feat|fix|perf)(?:\(([^)]*)\))?!?:\s*(.+)$/u', $subject, $m)) {
                continue;
            }
            $entries[] = [
                'hash' => $hash,
                'date' => $date,
                'type' => $m[1],
                'label' => self::TYPES[$m[1]],
                'scope' => $m[2],
                'text' => $m[3] . (mb_strlen($m[3]) >= 98 && !preg_match('/[.)]$/', $m[3]) ? '…' : ''),
            ];
            if (count($entries) >= $limit) {
                break;
            }
        }

        return $entries;
    }

    protected static function stats(): array
    {
        $first = trim(self::git(['log', '--reverse', '--date=short', '--format=%ad', '--max-parents=0']));
        $first = strtok($first, "\n") ?: null;

        return [
            'commits' => (int) trim(self::git(['rev-list', '--count', 'HEAD'])),
            'since' => $first,
            'updated' => trim(self::git(['log', '-1', '--date=short', '--format=%ad'])),
        ];
    }

    protected static function git(array $args): string
    {
        $cmd = 'git -c safe.directory=* -C ' . escapeshellarg(base_path())
            . ' ' . implode(' ', array_map('escapeshellarg', $args)) . ' 2>/dev/null';

        return (string) shell_exec($cmd);
    }
}
