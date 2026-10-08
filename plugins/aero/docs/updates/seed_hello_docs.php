<?php

use Aero\Docs\Models\Article;
use Aero\Docs\Models\Category;
use October\Rain\Database\Updates\Seeder;

return new class extends Seeder
{
    /**
     * Documentación de las funciones del panel del tenant de Aero.Hello.
     * El contenido vive como Markdown en plugins/aero/docs/content/hello y se
     * importa una sola vez como artículos del plugin Docs.
     */
    public function run(): void
    {
        $root = Category::firstOrNew(['tenant_id' => null, 'slug' => 'hello']);
        $root->fill([
            'name'        => 'Hello',
            'icon'        => '💬',
            'description' => 'Guía de mensajería: cuentas, bandeja, envíos, llamadas y redes.',
            'is_active'   => true,
            'is_global'   => true,
        ]);
        $root->save();

        $forms = Category::firstOrNew(['tenant_id' => null, 'slug' => 'hello-funciones']);
        $forms->fill([
            'parent_id'   => $root->id,
            'name'        => 'Funciones del panel',
            'icon'        => '📋',
            'description' => 'Qué hace cada pantalla de Hello y cómo se usa.',
            'is_active'   => true,
            'is_global'   => true,
        ]);
        $forms->save();

        $contentDir = plugins_path('aero/docs/content/hello');

        $articles = [
            ['file' => 'hello-vista-general', 'title' => 'Hello — Vista general',       'sort' => 0,  'featured' => true],
            ['file' => 'hello-connect',        'title' => 'Conectar cuenta',            'sort' => 10],
            ['file' => 'hello-comparativa',    'title' => 'WhatsApp Web vs Cloud API',  'sort' => 15],
            ['file' => 'hello-compose',        'title' => 'Redactar',                   'sort' => 20],
            ['file' => 'hello-conversations',  'title' => 'Bandeja de conversaciones',  'sort' => 30],
            ['file' => 'hello-calls',          'title' => 'Llamadas',                   'sort' => 40],
            ['file' => 'hello-contacts',       'title' => 'Contactos',                  'sort' => 50],
            ['file' => 'hello-templates',      'title' => 'Plantillas',                 'sort' => 60],
            ['file' => 'hello-posts',          'title' => 'Publicaciones',              'sort' => 70],
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
                'plugin_version' => $this->pluginVersion('aero/hello'),
                'sort_order'     => $item['sort'],
                'is_published'   => true,
                'is_global'    => true,
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
