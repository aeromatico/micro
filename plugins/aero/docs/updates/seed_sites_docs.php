<?php

use Aero\Docs\Models\Article;
use Aero\Docs\Models\Category;
use October\Rain\Database\Updates\Seeder;

return new class extends Seeder
{
    /**
     * Documentación de los formularios del panel del tenant (Sitio Web) de
     * Aero.Sites. No cubre el panel de superadmin.
     *
     * El contenido vive como Markdown en plugins/aero/docs/content/sites y se
     * importa una sola vez como artículos del plugin Docs. Así el texto queda
     * versionado en el repo (fácil de revisar y editar) y a la vez publicado en
     * el sitio de documentación.
     */
    public function run(): void
    {
        $root = Category::firstOrNew(['tenant_id' => null, 'slug' => 'sites']);
        $root->fill([
            'name'        => 'Sites',
            'icon'        => '🌐',
            'description' => 'Guía del panel Sitio Web para dueños de micrositios.',
            'is_active'   => true,
            'is_global'   => true,
        ]);
        $root->save();

        $forms = Category::firstOrNew(['tenant_id' => null, 'slug' => 'sites-formularios']);
        $forms->fill([
            'parent_id'   => $root->id,
            'name'        => 'Funciones del panel',
            'icon'        => '📋',
            'description' => 'Qué hace cada formulario del panel Sitio Web y cómo se usa.',
            'is_active'   => true,
            'is_global'   => true,
        ]);
        $forms->save();

        $contentDir = plugins_path('aero/docs/content/sites');

        $articles = [
            ['file' => 'sites-vista-general',            'title' => 'El panel Sitio Web',                     'sort' => 0,  'featured' => true],
            ['file' => 'sites-formulario-sitesettings',  'title' => 'Configuración de sitio',                'sort' => 10],
            ['file' => 'sites-formulario-branding',      'title' => 'Identidad visual (Branding)',           'sort' => 20],
            ['file' => 'sites-formulario-inicio',        'title' => 'Página de inicio (editor de contenidos)', 'sort' => 30],
            ['file' => 'sites-formulario-plantilla',     'title' => 'Plantilla del sitio (Layout)',          'sort' => 40],
            ['file' => 'sites-formulario-pagina',        'title' => 'Páginas',                               'sort' => 50],
            ['file' => 'sites-canales-notificacion',     'title' => 'Canales de notificación',               'sort' => 60],
            ['file' => 'sites-formulario-seoconfig',     'title' => 'Configuración SEO',                     'sort' => 70],
            ['file' => 'sites-formulario-contactconfig', 'title' => 'Configuración de contacto',             'sort' => 80],
        ];

        foreach ($articles as $item) {
            $path = $contentDir . '/' . $item['file'] . '.md';
            if (!is_file($path)) {
                continue;
            }

            $article = Article::firstOrNew(['tenant_id' => null, 'slug' => $item['file']]);
            $article->fill([
                'category_id'  => $forms->id,
                'title'        => $item['title'],
                'content'      => file_get_contents($path),
                'plugin_version' => $this->pluginVersion('aero/sites'),
                'sort_order'   => $item['sort'],
                'is_published' => true,
                'is_global'    => true,
                'is_featured'  => (bool) ($item['featured'] ?? false),
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
