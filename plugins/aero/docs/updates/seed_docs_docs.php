<?php

use Aero\Docs\Models\Article;
use Aero\Docs\Models\Category;
use October\Rain\Database\Updates\Seeder;

return new class extends Seeder
{
    /**
     * Documentación del propio plugin Docs (panel del tenant).
     * El contenido vive como Markdown en plugins/aero/docs/content/docs.
     */
    public function run(): void
    {
        $root = Category::firstOrNew(['tenant_id' => null, 'slug' => 'docs']);
        $root->fill([
            'name'        => 'Docs',
            'icon'        => '📚',
            'description' => 'Centro de ayuda: cómo administrar la documentación de tu sitio.',
            'is_active'   => true,
            'is_global'   => true,
        ]);
        $root->save();

        $forms = Category::firstOrNew(['tenant_id' => null, 'slug' => 'docs-funciones']);
        $forms->fill([
            'parent_id'   => $root->id,
            'name'        => 'Funciones del panel',
            'icon'        => '📋',
            'description' => 'Qué hace cada pantalla de Docs y cómo se usa.',
            'is_active'   => true,
            'is_global'   => true,
        ]);
        $forms->save();

        $contentDir = plugins_path('aero/docs/content/docs');

        $articles = [
            ['file' => 'docs-vista-general',  'title' => 'Docs — Vista general',      'sort' => 0,  'featured' => true],
            ['file' => 'docs-articulos',      'title' => 'Artículos',                 'sort' => 10],
            ['file' => 'docs-categorias',     'title' => 'Categorías',                'sort' => 20],
            ['file' => 'docs-ordenar-arbol',  'title' => 'Ordenar árbol',             'sort' => 30],
        ];

        foreach ($articles as $item) {
            $path = $contentDir . '/' . $item['file'] . '.md';
            if (!is_file($path)) {
                continue;
            }

            $article = Article::firstOrNew(['tenant_id' => null, 'slug' => $item['file']]);
            $article->fill([
                'category_id'    => $forms->id,
                'title'          => $item['title'],
                'content'        => file_get_contents($path),
                'plugin_version' => $this->pluginVersion('aero/docs'),
                'sort_order'     => $item['sort'],
                'is_published'   => true,
                'is_global'      => true,
                'is_featured'    => (bool) ($item['featured'] ?? false),
            ]);
            $article->save();
        }
    }

    /** Última versión declarada en updates/version.yaml del plugin. */
    protected function pluginVersion(string $handle): ?string
    {
        $path = plugins_path($handle . '/updates/version.yaml');
        if (!is_file($path)) {
            return null;
        }

        preg_match_all('/^\s*([0-9]+\.[0-9]+\.[0-9]+):/m', file_get_contents($path), $m);
        if (empty($m[1])) {
            return null;
        }

        usort($m[1], 'version_compare');

        return end($m[1]) ?: null;
    }
};
