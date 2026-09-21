<?php

use Aero\Docs\Models\Article;
use Aero\Docs\Models\Category;
use October\Rain\Database\Updates\Seeder;

return new class extends Seeder
{
    /**
     * Documentación de las funciones del panel del tenant de Aero.Chatbots.
     * El contenido vive como Markdown en plugins/aero/docs/content/chatbots
     * y se importa una sola vez como artículos del plugin Docs.
     */
    public function run(): void
    {
        $root = Category::firstOrNew(['tenant_id' => null, 'slug' => 'chatbots']);
        $root->fill([
            'name'        => 'Chatbots',
            'icon'        => '🤖',
            'description' => 'Guía de los bots de autorespuesta: modos, reglas y registro.',
            'is_active'   => true,
            'is_global'   => true,
        ]);
        $root->save();

        $forms = Category::firstOrNew(['tenant_id' => null, 'slug' => 'chatbots-funciones']);
        $forms->fill([
            'parent_id'   => $root->id,
            'name'        => 'Funciones del panel',
            'icon'        => '📋',
            'description' => 'Qué hace cada pantalla de Chatbots y cómo se usa.',
            'is_active'   => true,
            'is_global'   => true,
        ]);
        $forms->save();

        $contentDir = plugins_path('aero/docs/content/chatbots');

        $articles = [
            ['file' => 'chatbots-vista-general', 'title' => 'Chatbots — Vista general', 'sort' => 0,  'featured' => true],
            ['file' => 'chatbots-bots',          'title' => 'Bots',                      'sort' => 10],
            ['file' => 'chatbots-reglas',        'title' => 'Reglas',                    'sort' => 20],
            ['file' => 'chatbots-logs',          'title' => 'Registro de respuestas',    'sort' => 30],
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
                'plugin_version' => $this->pluginVersion('aero/chatbots'),
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
