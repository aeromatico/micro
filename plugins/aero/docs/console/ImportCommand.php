<?php namespace Aero\Docs\Console;

use Aero\Docs\Classes\GuideSources;
use Aero\Docs\Models\Article;
use Aero\Docs\Models\Category;
use Aero\Docs\Models\Guide;
use Illuminate\Console\Command;
use October\Rain\Parse\Yaml;

/**
 * Importa a la BD lo que docs-sync escribió en plugins/aero/docs/content:
 *   content/<plugin>/*.md              → artículos
 *   content/guides/<plugin>/*.html     → guías interactivas
 * Regla de oro: NUNCA pisa nada publicado. Lo nuevo entra como borrador y las
 * ediciones sobre lo publicado quedan como cambios pendientes de aprobar.
 */
class ImportCommand extends Command
{
    protected $signature = 'docs:import {plugin? : Plugin a importar (ej. pay); vacío = todos} {--dry-run : Solo mostrar qué haría} {--mark-reviewed : Marca los artículos SIN cambios como revisados para la versión actual del plugin}';

    protected $description = 'Importa artículos y guías de docs-sync como borrador / cambios pendientes.';

    protected int $errors = 0;

    public function handle(): int
    {
        $only = $this->argument('plugin');
        $contentDir = plugins_path('aero/docs/content');

        $plugins = [];
        foreach (glob($contentDir . '/*', GLOB_ONLYDIR) as $dir) {
            $name = basename($dir);
            if ($name !== 'guides' && (!$only || $only === $name)) {
                $plugins[] = $name;
            }
        }
        foreach (glob($contentDir . '/guides/*', GLOB_ONLYDIR) as $dir) {
            $name = basename($dir);
            if (!in_array($name, $plugins, true) && (!$only || $only === $name)) {
                $plugins[] = $name;
            }
        }

        foreach ($plugins as $plugin) {
            $this->importArticles($plugin, $contentDir . '/' . $plugin);
            $this->importGuides($plugin, $contentDir . '/guides/' . $plugin);
        }

        return $this->errors ? self::FAILURE : self::SUCCESS;
    }

    protected function importArticles(string $plugin, string $dir): void
    {
        if (!is_dir(plugins_path('aero/' . $plugin))) {
            return; // p. ej. content/plugins/: no es un plugin
        }
        $files = glob($dir . '/*.md') ?: [];
        if (!$files) {
            return;
        }

        $version = $this->pluginVersion($plugin);
        $category = $this->ensureCategory($plugin);

        foreach ($files as $file) {
            $slug = basename($file, '.md');
            [$meta, $body] = $this->splitFrontMatter((string) file_get_contents($file));
            $title = $meta['title'] ?? $this->firstHeading($body) ?? $slug;
            $existing = Article::whereNull('tenant_id')->where('slug', $slug)->first();

            if (!$existing) {
                $this->line("  + artículo nuevo (borrador): {$slug}");
                if (!$this->option('dry-run')) {
                    $article = new Article();
                    $article->fill([
                        'tenant_id'      => null,
                        'category_id'    => $category?->id,
                        'title'          => $title,
                        'slug'           => $slug,
                        'content'        => $body,
                        'plugin_version' => $version,
                        'sort_order'     => (int) ($meta['sort'] ?? $this->nextSort($category?->id)),
                        'is_published'   => false,
                        'is_global'      => true,
                        'is_featured'    => (bool) ($meta['featured'] ?? false),
                    ]);
                    $article->save();
                }
                continue;
            }

            $same = trim($existing->content) === trim($body);

            if ($same) {
                // Contenido idéntico NO significa revisado: solo se sube la versión documentada cuando quien
                // importa afirma que revisó la documentación de ese plugin (--mark-reviewed). Si no, se
                // ocultaría documentación realmente desactualizada.
                if ($this->option('mark-reviewed') && $version && $existing->plugin_version !== $version) {
                    $this->line("  = {$slug}: sin cambios de contenido, versión documentada → {$version}");
                    $this->direct($existing->id, ['plugin_version' => $version]);
                }
                continue;
            }

            if ($existing->is_published) {
                $this->line("  ~ {$slug}: cambios pendientes de aprobar");
                $this->direct($existing->id, [
                    'pending_content'        => $body,
                    'pending_plugin_version' => $version,
                    'pending_at'             => now(),
                ]);
            } else {
                $this->line("  ~ {$slug}: borrador actualizado");
                if (!$this->option('dry-run')) {
                    $existing->content = $body;
                    $existing->plugin_version = $version ?: $existing->plugin_version;
                    $existing->save();
                }
            }
        }
    }

    protected function importGuides(string $plugin, string $dir): void
    {
        foreach (glob($dir . '/*.html') ?: [] as $file) {
            $slug = basename($file, '.html');
            $html = (string) file_get_contents($file);

            $form = GuideSources::find($plugin, $slug);
            if (!$form) {
                $this->error("  ! {$slug}: no corresponde a ningún formulario de {$plugin} (slug esperado guia-{$plugin}-<controlador>)");
                $this->errors++;
                continue;
            }
            if (strlen($html) < 500 || stripos($html, '<title') === false || strlen($html) > 2_000_000) {
                $this->error("  ! {$slug}: HTML inválido (vacío, sin <title> o mayor de 2 MB)");
                $this->errors++;
                continue;
            }

            if ($jsError = $this->scriptSyntaxError($html)) {
                $this->error("  ! {$slug}: error de sintaxis en el JavaScript de la guía: {$jsError}");
                $this->errors++;
                continue;
            }

            $meta = $this->guideMeta($html);
            $title = $meta['title'] ?? $form['name'];
            $articleId = !empty($meta['article'])
                ? Article::whereNull('tenant_id')->where('slug', $meta['article'])->value('id')
                : null;

            $guide = Guide::whereNull('tenant_id')->where('slug', $slug)->first();

            if (!$guide) {
                $this->line("  + guía nueva (borrador): {$slug}");
                if (!$this->option('dry-run')) {
                    $g = new Guide();
                    $g->fill([
                        'tenant_id'    => null,
                        'is_global'    => true,
                        'slug'         => $slug,
                        'title'        => $title,
                        'plugin'       => $plugin,
                        'form_ref'     => $form['form_ref'],
                        'article_id'   => $articleId,
                        'pending_html' => $html,
                        'pending_at'   => now(),
                        'source_hash'  => $form['source_hash'],
                        'status'       => 'draft',
                        'sort_order'   => (int) ($meta['sort'] ?? 0),
                    ]);
                    $g->save();
                }
                continue;
            }

            $current = $guide->html ?: $guide->pending_html;
            if ($current && sha1(rtrim($current)) === sha1(rtrim($html))) {
                $this->direct($guide->id, ['source_hash' => $form['source_hash']], 'aero_docs_guides');
                continue;
            }

            $this->line("  ~ guía {$slug}: propuesta pendiente de revisión");
            $this->direct($guide->id, [
                'pending_html' => $html,
                'pending_at'   => now(),
                'source_hash'  => $form['source_hash'],
                'article_id'   => $articleId ?: $guide->article_id,
            ], 'aero_docs_guides');
        }
    }

    /** Actualiza sin pasar por eventos del modelo (no crea versión ni regenera HTML). */
    protected function direct(int $id, array $data, string $table = 'aero_docs_articles'): void
    {
        if (!$this->option('dry-run')) {
            \DB::table($table)->where('id', $id)->update($data);
        }
    }

    protected function ensureCategory(string $plugin): ?Category
    {
        $sub = Category::whereNull('tenant_id')->where('slug', $plugin . '-funciones')->first();
        if ($sub) {
            return $sub;
        }
        if ($this->option('dry-run')) {
            return null;
        }

        $root = Category::whereNull('tenant_id')->where('slug', $plugin)->first();
        if (!$root) {
            $root = new Category();
            $root->fill(['tenant_id' => null, 'slug' => $plugin, 'name' => ucfirst($plugin), 'is_active' => true, 'is_global' => true]);
            $root->save();
        }
        $sub = new Category();
        $sub->fill(['tenant_id' => null, 'slug' => $plugin . '-funciones', 'parent_id' => $root->id,
            'name' => 'Funciones del panel', 'is_active' => true, 'is_global' => true]);
        $sub->save();

        return $sub;
    }

    protected function nextSort(?int $categoryId): int
    {
        return $categoryId ? ((int) Article::where('category_id', $categoryId)->max('sort_order')) + 10 : 0;
    }

    /** @return array{0: array, 1: string} */
    protected function splitFrontMatter(string $text): array
    {
        if (preg_match('/\A---\R(.*?)\R---\R?(.*)\z/s', $text, $m)) {
            $meta = (new Yaml)->parse($m[1]);
            return [is_array($meta) ? $meta : [], ltrim($m[2])];
        }
        return [[], $text];
    }

    protected function firstHeading(string $body): ?string
    {
        return preg_match('/^#\s+(.+)$/m', $body, $m) ? trim($m[1]) : null;
    }

    /** Corre `node --check` sobre los <script> en línea. Null si está bien o si no hay node. */
    protected function scriptSyntaxError(string $html): ?string
    {
        $node = trim((string) shell_exec('command -v node 2>/dev/null'));
        if (!$node || !preg_match_all('/<script(?![^>]*\ssrc=)[^>]*>(.*?)<\/script>/is', $html, $m)) {
            return null;
        }

        foreach ($m[1] as $i => $code) {
            $tmp = tempnam(sys_get_temp_dir(), 'guide') . '.js';
            file_put_contents($tmp, $code);
            $out = [];
            exec(escapeshellarg($node) . ' --check ' . escapeshellarg($tmp) . ' 2>&1', $out, $rc);
            @unlink($tmp);
            if ($rc !== 0) {
                return 'script #' . ($i + 1) . ': ' . trim(implode(' ', array_slice($out, 0, 4)));
            }
        }

        return null;
    }

    /** Metadatos en un comentario: <!--guide {"title":"...","article":"slug","sort":10} --> */
    protected function guideMeta(string $html): array
    {
        if (preg_match('/<!--\s*guide\s+(\{.*?\})\s*-->/s', $html, $m)) {
            $meta = json_decode($m[1], true);
            return is_array($meta) ? $meta : [];
        }
        return [];
    }

    protected function pluginVersion(string $plugin): ?string
    {
        $path = plugins_path('aero/' . $plugin . '/updates/version.yaml');
        if (!is_file($path)) {
            return null;
        }
        preg_match_all('/^\s*([0-9]+\.[0-9]+\.[0-9]+):/m', (string) file_get_contents($path), $m);
        if (empty($m[1])) {
            return null;
        }
        usort($m[1], 'version_compare');

        return end($m[1]);
    }
}
