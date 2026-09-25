<?php

use Aero\Docs\Models\Article;
use Aero\Docs\Models\Category;
use October\Rain\Database\Updates\Seeder;

return new class extends Seeder
{
    /**
     * Guía pública de la API de Market (Aero.Api): autenticación, permisos,
     * límites y webhooks salientes. Contenido en plugins/aero/docs/content/api,
     * importado una sola vez como artículos globales (visibles para todos los
     * tenants, no solo el que los crea) — misma mecánica que seed_pay_docs.php.
     */
    public function run(): void
    {
        $root = Category::firstOrNew(['tenant_id' => null, 'slug' => 'api']);
        $root->fill([
            'name'        => 'API',
            'icon'        => '🔌',
            'description' => 'Cómo integrar tu propio sistema con Market: API keys, permisos y webhooks.',
            'is_active'   => true,
            'is_global'   => true,
        ]);
        $root->save();

        $contentDir = plugins_path('aero/docs/content/api');

        $articles = [
            ['file' => 'api-vista-general',   'title' => 'API de Market — Vista general',    'sort' => 0, 'featured' => true],
            ['file' => 'api-autenticacion',    'title' => 'Autenticación, permisos y límites', 'sort' => 10],
            ['file' => 'api-webhooks',         'title' => 'Webhooks: recibir eventos en tiempo real', 'sort' => 20],
        ];

        foreach ($articles as $item) {
            $path = $contentDir . '/' . $item['file'] . '.md';
            if (!is_file($path)) {
                continue;
            }

            $article = Article::firstOrNew(['tenant_id' => null, 'slug' => $item['file']]);
            $article->fill([
                'category_id'    => $root->id,
                'title'          => $item['title'],
                'content'        => file_get_contents($path),
                'plugin_version' => $this->pluginVersion('aero/api'),
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
