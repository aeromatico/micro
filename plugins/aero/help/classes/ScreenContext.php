<?php namespace Aero\Help\Classes;

use Aero\Help\Models\Topic;

/**
 * Traduce la URL del backend a `plugin/controlador/acción` y, con eso, sugiere
 * qué ayuda ofrecer: los temas cuyos patrones coinciden y, sin configurar
 * nada, las guías publicadas cuyo formulario de origen (`form_ref`) es el de
 * la pantalla donde está la persona.
 */
class ScreenContext
{
    /** @return array{plugin: string, controller: string, action: string, key: string} */
    public static function parse(string $url): array
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $uri = trim((string) \Backend::uri(), '/');
        if ($uri !== '' && str_starts_with($path, $uri . '/')) {
            $path = substr($path, strlen($uri) + 1);
        } elseif ($path === $uri) {
            $path = '';
        }

        $seg = $path === '' ? [] : explode('/', $path);
        if (($seg[0] ?? '') === 'aero') {
            array_shift($seg);
        }

        $plugin = strtolower($seg[0] ?? '');
        $controller = strtolower($seg[1] ?? 'index');
        $action = strtolower($seg[2] ?? 'index');

        return [
            'plugin'     => $plugin,
            'controller' => $controller,
            'action'     => $action,
            'key'        => $plugin === '' ? '' : "{$plugin}/{$controller}/{$action}",
        ];
    }

    /**
     * Tarjetas sugeridas para la pantalla, de lo más específico a lo general.
     *
     * @return array<int, array{kind: string, id: int, title: string, summary: ?string}>
     */
    public static function suggestions(array $screen, int $limit = 4): array
    {
        $out = [];
        if ($screen['key'] === '') {
            return $out;
        }

        foreach (Topic::published()->orderBy('sort_order')->orderBy('title')->get() as $topic) {
            foreach ((array) $topic->screens as $pattern) {
                if (is_string($pattern) && fnmatch(strtolower(trim($pattern)), $screen['key'])) {
                    $out['topic:' . $topic->id] = ['kind' => 'topic', 'id' => $topic->id, 'title' => $topic->title, 'summary' => $topic->summary];
                    break;
                }
            }
        }

        if (Integrations::docs()) {
            $prefix = strtolower("aero.{$screen['plugin']}/{$screen['controller']}/");
            $guides = \Aero\Docs\Models\Guide::platform()->published()->whereNotNull('form_ref')->get()
                ->filter(fn ($g) => str_starts_with(strtolower((string) $g->form_ref), $prefix));

            foreach ($guides as $guide) {
                $out['guide:' . $guide->id] = ['kind' => 'guide', 'id' => $guide->id, 'title' => $guide->title, 'summary' => 'Guía interactiva de esta pantalla'];
            }
        }

        return array_slice(array_values($out), 0, $limit);
    }
}
