<?php namespace Aero\Docs\Classes;

use October\Rain\Parse\Yaml;

/**
 * Descubre, sin IA, qué formularios de un plugin merecen guía interactiva y
 * calcula el hash de las fuentes de las que depende cada una. Si el hash
 * cambia, la guía está desactualizada y hay que regenerarla.
 *
 * Un formulario "principal" es el de un controlador con FormController cuyo
 * fields.yaml vive en el propio plugin. Se excluyen los ajustes y lo listado
 * en content/guides/exclude.json (lista de "plugin/controlador").
 */
class GuideSources
{
    /** @return array<int, array<string, mixed>> */
    public static function forPlugin(string $plugin): array
    {
        $root = plugins_path('aero/' . $plugin);
        if (!is_dir($root . '/controllers')) {
            return [];
        }

        $exclude = static::excluded();
        $out = [];

        foreach (glob($root . '/controllers/*', GLOB_ONLYDIR) as $dir) {
            $controller = basename($dir);
            $configPath = $dir . '/config_form.yaml';

            if (!is_file($configPath) || in_array("{$plugin}/{$controller}", $exclude, true)
                || preg_match('/settings|setting$/i', $controller)) {
                continue;
            }

            $config = (new Yaml)->parse(file_get_contents($configPath));
            if (!is_array($config)) {
                continue;
            }
            $fieldsPath = static::resolvePath($config['form'] ?? null);
            if (!$fieldsPath || !is_file($fieldsPath) || !str_starts_with(realpath($fieldsPath), realpath($root))) {
                continue;
            }

            $modelFile = static::modelFile($config['modelClass'] ?? null);
            $inputs = [$configPath, $fieldsPath];

            foreach (['config_relation.yaml'] as $extra) {
                if (is_file($dir . '/' . $extra)) {
                    $inputs[] = $dir . '/' . $extra;
                    foreach ((array) (new Yaml)->parse(file_get_contents($dir . '/' . $extra)) as $rel) {
                        if (!is_array($rel)) {
                            continue;
                        }
                        foreach (['list', 'form'] as $k) {
                            $p = static::resolvePath($rel['view'][$k] ?? ($rel['manage'][$k] ?? null));
                            if ($p && is_file($p)) {
                                $inputs[] = $p;
                            }
                        }
                        $p = static::resolvePath($rel['manage']['form'] ?? null);
                        if ($p && is_file($p)) {
                            $inputs[] = $p;
                        }
                    }
                }
            }
            foreach (glob($dir . '/_*.htm') ?: [] as $partial) {
                $inputs[] = $partial;
            }

            $parts = [];
            foreach (array_unique($inputs) as $file) {
                $parts[] = str_replace($root, '', $file) . ':' . sha1((string) file_get_contents($file));
            }
            if ($modelFile) {
                $parts[] = 'options:' . sha1(static::optionMethods((string) file_get_contents($modelFile)));
            }
            sort($parts);

            $out[] = [
                'plugin'      => $plugin,
                'controller'  => $controller,
                'slug'        => 'guia-' . $plugin . '-' . $controller,
                'form_ref'    => 'Aero.' . ucfirst($plugin) . '/' . $controller . '/create',
                'name'        => $config['name'] ?? $controller,
                'form_yaml'   => str_replace(base_path() . '/', '', $fieldsPath),
                'config_yaml' => str_replace(base_path() . '/', '', $configPath),
                'model_file'  => $modelFile ? str_replace(base_path() . '/', '', $modelFile) : null,
                'source_hash' => sha1(implode('|', $parts)),
            ];
        }

        return $out;
    }

    /** Un solo formulario por slug, o null. */
    public static function find(string $plugin, string $slug): ?array
    {
        foreach (static::forPlugin($plugin) as $form) {
            if ($form['slug'] === $slug) {
                return $form;
            }
        }
        return null;
    }

    /** Plugins Aero con al menos un formulario candidato. */
    public static function plugins(): array
    {
        $names = [];
        foreach (glob(plugins_path('aero/*'), GLOB_ONLYDIR) as $dir) {
            $name = basename($dir);
            if ($name !== 'docs' && static::forPlugin($name)) {
                $names[] = $name;
            }
        }
        return $names;
    }

    public static function excluded(): array
    {
        $file = plugins_path('aero/docs/content/guides/exclude.json');
        $list = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
        return is_array($list) ? $list : [];
    }

    /** `$/aero/pay/models/x.yaml` → ruta absoluta. */
    protected static function resolvePath(mixed $path): ?string
    {
        if (!is_string($path) || $path === '') {
            return null;
        }
        if (str_starts_with($path, '$/')) {
            return plugins_path(substr($path, 2));
        }
        return null;
    }

    protected static function modelFile(?string $class): ?string
    {
        if (!$class || !preg_match('/^Aero\\\\(\w+)\\\\Models\\\\(\w+)$/', $class, $m)) {
            return null;
        }
        $file = plugins_path('aero/' . strtolower($m[1]) . '/models/' . $m[2] . '.php');
        return is_file($file) ? $file : null;
    }

    /** Solo los métodos get*Options: son lo que define las opciones de los dropdowns. */
    protected static function optionMethods(string $php): string
    {
        $found = [];
        if (preg_match_all('/function\s+get\w*Options\s*\([^)]*\)[^{]*\{/', $php, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[0] as [$head, $pos]) {
                $depth = 1;
                $i = $pos + strlen($head);
                $len = strlen($php);
                while ($i < $len && $depth > 0) {
                    $depth += $php[$i] === '{' ? 1 : ($php[$i] === '}' ? -1 : 0);
                    $i++;
                }
                $found[] = substr($php, $pos, $i - $pos);
            }
        }
        return implode("\n", $found);
    }
}
