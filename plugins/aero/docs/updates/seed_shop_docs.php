<?php

use Aero\Docs\Models\Article;
use Aero\Docs\Models\Category;
use October\Rain\Database\Updates\Seeder;

return new class extends Seeder
{
    /**
     * Documentación de las funciones del panel del tenant de Aero.Shop.
     * El contenido vive como Markdown en plugins/aero/docs/content/shop y se
     * importa una sola vez como artículos del plugin Docs.
     */
    public function run(): void
    {
        $root = Category::firstOrNew(['tenant_id' => null, 'slug' => 'shop']);
        $root->fill([
            'name'        => 'Tienda',
            'icon'        => '🛒',
            'description' => 'Guía de la tienda: catálogo, variantes, pedidos, inventario y cobro.',
            'is_active'   => true,
            'is_global'   => true,
        ]);
        $root->save();

        $forms = Category::firstOrNew(['tenant_id' => null, 'slug' => 'shop-funciones']);
        $forms->fill([
            'parent_id'   => $root->id,
            'name'        => 'Funciones del panel',
            'icon'        => '📋',
            'description' => 'Qué hace cada pantalla de la Tienda y cómo se usa.',
            'is_active'   => true,
            'is_global'   => true,
        ]);
        $forms->save();

        $contentDir = plugins_path('aero/docs/content/shop');

        $articles = [
            ['file' => 'shop-vista-general',  'title' => 'Tienda — Vista general',       'sort' => 0,  'featured' => true],
            ['file' => 'shop-configuracion',  'title' => 'Configuración de tienda',      'sort' => 10],
            ['file' => 'shop-productos',      'title' => 'Productos',                    'sort' => 20],
            ['file' => 'shop-variantes',      'title' => 'Variantes y opciones',         'sort' => 30],
            ['file' => 'shop-colecciones',    'title' => 'Colecciones',                  'sort' => 40],
            ['file' => 'shop-pedidos',        'title' => 'Pedidos',                      'sort' => 50],
            ['file' => 'shop-clientes',       'title' => 'Clientes',                     'sort' => 60],
            ['file' => 'shop-metodos-pago',   'title' => 'Métodos de pago',              'sort' => 70],
            ['file' => 'shop-inventario',     'title' => 'Inventario',                   'sort' => 80],
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
                'plugin_version' => $this->pluginVersion('aero/shop'),
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
