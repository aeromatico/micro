<?php namespace Aero\Services\Classes;

use Aero\Connector\Classes\AiResponseText;
use Aero\Connector\Classes\ConnectorClient;
use Aero\Connector\Models\Connector;
use Aero\Docs\Models\Article;
use Aero\Docs\Models\Category as DocCategory;
use Aero\Services\Models\Category;
use Aero\Services\Models\Service;
use System\Classes\PluginManager;

/**
 * Arma la oferta de un servicio (resumen, descripción, características, página HTML)
 * leyendo lo que realmente hay en los plugins ligados: README, permisos, menú,
 * componentes, comandos, modelos, rutas, historial de versiones y documentación.
 */
class OfferBuilder
{
    protected const RELATION_MEANING = [
        'built_with'  => 'el servicio está CONSTRUIDO CON este plugin (es su base técnica)',
        'integrates'  => 'el servicio SE INTEGRA CON este plugin (se conecta, pero funciona sin él)',
        'recommended' => 'el servicio es RECOMENDADO PARA quienes usan este plugin (sin conexión técnica)',
    ];

    public function __construct(protected ?Connector $connector = null)
    {
        $this->connector ??= Connector::where('type', 'ai_anthropic')->orderBy('id')->first();
    }

    /** Evidencia por plugin ligado, tal como se le entrega a la IA. */
    public function evidence(Service $service): array
    {
        $out = [];

        foreach ((array) $service->plugin_links as $link) {
            $code = $link['plugin'] ?? null;
            if (!$code) {
                continue;
            }
            $out[] = ['link' => $link] + $this->pluginEvidence($code);
        }

        return $out;
    }

    protected function pluginEvidence(string $code): array
    {
        $plugin = PluginManager::instance()->findByIdentifier($code);
        $dir = plugins_path(strtolower(str_replace('.', '/', $code)));
        $short = substr(strrchr($code, '.') ?: '.' . $code, 1);

        $ev = [
            'code'    => $code,
            'name'    => $plugin ? trans((string) ($plugin->pluginDetails()['name'] ?? $code)) : $code,
            'description' => $plugin ? trans((string) ($plugin->pluginDetails()['description'] ?? '')) : '',
        ];

        if ($plugin) {
            $ev['permissions'] = array_values(array_map(fn ($p) => trans((string) ($p['label'] ?? '')), (array) $plugin->registerPermissions()));
            $ev['menu'] = $this->menuLabels((array) $plugin->registerNavigation());
            $ev['components'] = array_keys((array) $plugin->registerComponents());
        }

        $ev['readme'] = $this->read($dir . '/README.md', 5000);
        $ev['models'] = $this->basenames($dir . '/models', '*.php');
        $ev['console'] = $this->basenames($dir . '/console', '*.php');
        $ev['http'] = $this->basenames($dir . '/http/controllers', '*.php');
        $ev['routes'] = $this->routeLines($dir);
        $ev['changelog'] = $this->changelog($dir . '/updates/version.yaml');
        $ev['docs'] = $this->docs($short);

        return $ev;
    }

    protected function menuLabels(array $nav): array
    {
        $labels = [];
        foreach ($nav as $item) {
            $labels[] = trans((string) ($item['label'] ?? ''));
            foreach ((array) ($item['sideMenu'] ?? []) as $sub) {
                $labels[] = '  - ' . trans((string) ($sub['label'] ?? ''));
            }
        }

        return $labels;
    }

    protected function read(string $path, int $max): string
    {
        return is_file($path) ? mb_substr((string) file_get_contents($path), 0, $max) : '';
    }

    protected function basenames(string $dir, string $glob): array
    {
        return array_map(fn ($f) => basename($f, '.php'), glob($dir . '/' . $glob) ?: []);
    }

    protected function routeLines(string $dir): array
    {
        $lines = [];
        foreach (array_merge(glob($dir . '/routes.php') ?: [], glob($dir . '/http/routes.php') ?: []) as $file) {
            foreach (file($file) as $line) {
                if (preg_match('/Route::|->(get|post|put|patch|delete)\(/', $line)) {
                    $lines[] = trim($line);
                }
            }
        }

        return array_slice($lines, 0, 60);
    }

    /** Las descripciones de cada versión resumen qué capacidad se agregó. */
    protected function changelog(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }

        $notes = [];
        foreach ((array) \Symfony\Component\Yaml\Yaml::parseFile($file) as $version => $items) {
            foreach ((array) $items as $item) {
                if (is_string($item) && !str_ends_with($item, '.php')) {
                    $notes[] = "v{$version}: {$item}";
                }
            }
        }

        return array_slice($notes, -30);
    }

    /** Artículos publicados de la categoría de docs que lleva el nombre del plugin (y sus hijas). */
    protected function docs(string $shortName): array
    {
        $root = DocCategory::withoutGlobalScopes()->whereRaw('LOWER(name) = ?', [mb_strtolower($shortName)])->pluck('id')->all();
        if (!$root) {
            return [];
        }

        $ids = array_merge($root, DocCategory::withoutGlobalScopes()->whereIn('parent_id', $root)->pluck('id')->all());

        return Article::withoutGlobalScopes()->whereIn('category_id', $ids)->where('is_published', true)->orderBy('sort_order')->get()
            ->map(fn ($a) => [
                'id'      => $a->id,
                'title'   => $a->title,
                'excerpt' => $a->excerpt,
                'content' => mb_substr(strip_tags((string) $a->content), 0, 900),
            ])->all();
    }

    // -----------------------------------------------------------------------

    /** @return array{summary:string,description:string,features:array,requirements:array,code:string,docs:array,raw:array} */
    public function build(Service $service): array
    {
        if (!$this->connector) {
            throw new \RuntimeException('No hay un conector de IA (ai_anthropic) configurado en Aero.Connector.');
        }

        $evidence = $this->evidence($service);
        if (!$evidence) {
            throw new \RuntimeException('El servicio no tiene plugins ligados: no hay nada que analizar.');
        }

        $response = (new ConnectorClient())->send($this->connector, [
            'messages'   => [
                ['role' => 'system', 'content' => $this->systemPrompt()],
                ['role' => 'user', 'content' => $this->userPrompt($service, $evidence)],
            ],
            'max_tokens' => 16000,
            'timeout'    => 400,
        ]);

        $text = AiResponseText::extract($this->connector, $response);
        if (!$text) {
            throw new \RuntimeException('La IA no devolvió texto: ' . ($response->error ?? 'respuesta vacía'));
        }

        return $this->fromJson($text, $evidence);
    }

    /** Convierte el JSON de la oferta (de la API o escrito en sesión) en el resultado validado. */
    public function fromJson(string $json, ?array $evidence = null, ?Service $service = null): array
    {
        $evidence ??= $this->evidence($service);

        $data = json_decode($this->stripFences($json), true);
        if (!is_array($data) || empty($data['summary']) || empty($data['code'])) {
            throw new \RuntimeException('La oferta no es un JSON válido con summary y code: ' . mb_substr($json, 0, 300));
        }

        $validDocs = collect($evidence)->pluck('docs')->flatten(1)->pluck('id')->all();
        $docIds = array_values(array_intersect((array) ($data['docs_article_ids'] ?? []), $validDocs));

        return [
            'summary'      => mb_substr(trim((string) $data['summary']), 0, 255),
            'description'  => trim((string) ($data['description'] ?? '')),
            'features'     => $this->items($data['features'] ?? []),
            'requirements' => $this->items($data['requirements'] ?? []),
            'code'         => $this->sanitize((string) $data['code']) . $this->docsSection($docIds),
            'docs'         => $docIds,
            'raw'          => $data,
        ];
    }

    /** Guarda el resultado en el servicio, con la categoría Panel y los documentos ligados. */
    public function apply(Service $service, array $offer, string $categoryName = 'Panel'): void
    {
        $service->fill([
            'summary'      => $offer['summary'],
            'description'  => $offer['description'],
            'features'     => $offer['features'] ?: null,
            'requirements' => $offer['requirements'] ?: null,
            'code'         => $offer['code'],
        ]);

        if ($category = Category::where('name', $categoryName)->first()) {
            $service->category_id = $category->id;
        }

        $service->save();
        $this->dumpForTailwind($service);

        if ($offer['docs']) {
            $service->articles()->syncWithoutDetaching($offer['docs']);
        }
    }

    /** Las clases Tailwind de `code` viven en la BD: se vuelca a un archivo que el build del tema escanea. */
    public function dumpForTailwind(Service $service): void
    {
        $dir = storage_path('app/service-offers');
        @mkdir($dir, 0775, true);
        file_put_contents("{$dir}/{$service->slug}.html", (string) $service->code);
    }

    protected function systemPrompt(): string
    {
        return <<<'P'
Eres redactor de producto para una plataforma SaaS boliviana. Armas la oferta comercial de un servicio a partir de la EVIDENCIA técnica de los plugins que lo componen.

Reglas:
- Español neutro, claro, sin exageraciones. Habla de beneficios concretos para un negocio.
- Usa SOLO capacidades que aparezcan en la evidencia (README, menú, permisos, componentes, modelos, rutas, changelog, documentación). No inventes funciones, integraciones, precios ni cifras.
- Cada plugin ligado trae su relación: «construido con» = es la base del servicio; «se integra con» = capacidad de conexión; «recomendado para» = quién más se beneficia (no lo presentes como una función del servicio).
- Si algo no está claro en la evidencia, omítelo.

Devuelve ÚNICAMENTE un objeto JSON (sin texto antes ni después, sin ```), con estas claves:
{
  "summary": "una línea, máx. 160 caracteres",
  "description": "Markdown, 2-4 párrafos: qué es, para quién, qué resuelve",
  "features": ["ítem corto", ...],            // 6-10 ítems de «Qué incluye»
  "requirements": ["ítem corto", ...],        // qué se necesita del cliente (puede ser [])
  "code": "HTML",
  "docs_article_ids": [ids]                   // SOLO ids de la lista de documentos entregada, los más útiles
}

«code» es un fragmento HTML (sin <html>/<head>/<body>, sin <script>, sin estilos en línea) con clases Tailwind CSS 3 y SOLO los colores del tema (modo oscuro por defecto): texto text-ink y text-ink-dim, tarjetas bg-canvas-elev con border border-edge, acento text-accent / bg-accent text-accent-fg. NUNCA uses gray-*, white, black ni colores fijos. Listo para incrustar. Secciones, en este orden, cada una en <section> con <h2>:
1. Hero: <h1> con el nombre del servicio + promesa en un párrafo.
2. Características: rejilla de tarjetas (título + 1-2 líneas), basadas en capacidades reales.
3. Casos de uso: 3-5 escenarios concretos (quién, qué problema, cómo lo resuelve).
4. Cómo se conecta con tu plataforma: solo si hay plugins «se integra con»/«recomendado para»; qué gana el cliente en cada caso.
5. Preguntas frecuentes: 5-7 preguntas con <details><summary>…</summary><p>…</p></details>, respuestas respaldadas por la evidencia.
No incluyas sección de documentación: la agrega el sistema.
P;
    }

    protected function userPrompt(Service $service, array $evidence): string
    {
        $parts = ["SERVICIO: {$service->name}", 'Resumen actual: ' . ($service->summary ?: '(vacío)'), '', 'PLUGINS LIGADOS:'];

        foreach ($evidence as $ev) {
            $rel = $ev['link']['relation'] ?? 'integrates';
            $parts[] = "\n=== {$ev['code']} — {$ev['name']} ===";
            $parts[] = 'Relación: ' . (self::RELATION_MEANING[$rel] ?? $rel);
            if (!empty($ev['link']['note'])) {
                $parts[] = 'Nota: ' . $ev['link']['note'];
            }
            $parts[] = 'Descripción: ' . $ev['description'];
            foreach (['menu' => 'Menú del panel', 'permissions' => 'Permisos', 'components' => 'Componentes', 'models' => 'Modelos', 'console' => 'Comandos', 'http' => 'Controladores API', 'routes' => 'Rutas', 'changelog' => 'Historial de versiones'] as $key => $label) {
                if (!empty($ev[$key])) {
                    $parts[] = "{$label}:\n" . implode("\n", (array) $ev[$key]);
                }
            }
            if ($ev['readme']) {
                $parts[] = "README:\n" . $ev['readme'];
            }
            if ($ev['docs']) {
                $parts[] = 'Documentos disponibles (id · título · extracto):';
                foreach ($ev['docs'] as $d) {
                    $parts[] = "[{$d['id']}] {$d['title']} — " . ($d['excerpt'] ?: $d['content']);
                }
            }
        }

        return implode("\n", $parts);
    }

    protected function docsSection(array $ids): string
    {
        if (!$ids) {
            return '';
        }

        $items = '';
        foreach (Article::withoutGlobalScopes()->whereIn('id', $ids)->orderBy('sort_order')->get() as $a) {
            $items .= '<li><a class="font-medium text-accent hover:underline" href="' . e($a->url) . '">' . e($a->title) . '</a>'
                . ($a->excerpt ? '<span class="text-ink-dim"> — ' . e($a->excerpt) . '</span>' : '') . '</li>';
        }

        return "\n<section class=\"py-12\"><h2 class=\"text-2xl font-bold\">Documentación</h2><ul class=\"mt-4 space-y-2\">{$items}</ul></section>\n";
    }

    protected function items($list): array
    {
        return array_values(array_filter(array_map(
            fn ($t) => trim((string) (is_array($t) ? ($t['text'] ?? '') : $t)) !== '' ? ['text' => trim((string) (is_array($t) ? $t['text'] : $t))] : null,
            (array) $list
        )));
    }

    protected function stripFences(string $text): string
    {
        $text = trim($text);

        return preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', $text, $m) ? $m[1] : $text;
    }

    /** Quita scripts, iframes, manejadores on*= y URLs javascript:. */
    protected function sanitize(string $html): string
    {
        $html = preg_replace('#<(script|style|iframe|object|embed)\b.*?</\1>#is', '', $html);
        $html = preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html);
        $html = preg_replace('#(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2#i', '$1="#"', $html);

        return trim($html);
    }
}
