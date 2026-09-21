<?php

use Aero\Docs\Models\Article;
use Aero\Docs\Models\Category;
use October\Rain\Database\Updates\Seeder;

return new class extends Seeder
{
    /**
     * Documentación de las funciones del panel del tenant de Aero.Pay
     * (Bolivia Pay). El contenido vive como Markdown en
     * plugins/aero/docs/content/pay y se importa una sola vez como artículos
     * del plugin Docs.
     */
    public function run(): void
    {
        $root = Category::firstOrNew(['tenant_id' => null, 'slug' => 'pay']);
        $root->fill([
            'name'        => 'Pagos',
            'icon'        => '💳',
            'description' => 'Guía de Bolivia Pay: cuentas, QR, transacciones y configuración.',
            'is_active'   => true,
            'is_global'   => true,
        ]);
        $root->save();

        $forms = Category::firstOrNew(['tenant_id' => null, 'slug' => 'pay-funciones']);
        $forms->fill([
            'parent_id'   => $root->id,
            'name'        => 'Funciones del panel',
            'icon'        => '📋',
            'description' => 'Qué hace cada pantalla de Bolivia Pay y cómo se usa.',
            'is_active'   => true,
            'is_global'   => true,
        ]);
        $forms->save();

        $contentDir = plugins_path('aero/docs/content/pay');

        $articles = [
            ['file' => 'pay-vista-general',    'title' => 'Bolivia Pay — Vista general',   'sort' => 0,  'featured' => true],
            ['file' => 'pay-cuentas-bancarias', 'title' => 'Cuentas bancarias',            'sort' => 10],
            ['file' => 'pay-sucursales',        'title' => 'Sucursales',                    'sort' => 20],
            ['file' => 'pay-generar-qr',        'title' => 'Generar código QR',             'sort' => 30],
            ['file' => 'pay-detalle-qr',        'title' => 'Detalle del QR',                'sort' => 40],
            ['file' => 'pay-transacciones',     'title' => 'Transacciones',                 'sort' => 50],
            ['file' => 'pay-tokens-api',        'title' => 'Tokens de API',                 'sort' => 60],
            ['file' => 'pay-configuracion',     'title' => 'Configuración de pagos QR',     'sort' => 70],
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
                'plugin_version' => $this->pluginVersion('aero/pay'),
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
