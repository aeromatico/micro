<?php

use Aero\Docs\Models\Article;
use Aero\Docs\Models\Category;
use October\Rain\Database\Updates\Seeder;

return new class extends Seeder
{
    /**
     * Documento paralelo a "Funciones del panel" (ver seed_pay_docs.php)
     * dentro de la categoría raíz "pay": no describe una pantalla del panel,
     * sino cómo instalar y usar el plugin de WordPress/WooCommerce que
     * cobra con Bolivia Pay desde una tienda externa.
     */
    public function run(): void
    {
        $root = Category::firstOrNew(['tenant_id' => null, 'slug' => 'pay']);
        if (!$root->exists) {
            // No debería pasar (seed_pay_docs corre antes), pero sin la
            // categoría raíz no hay dónde colgar la nueva.
            return;
        }

        $integrations = Category::firstOrNew(['tenant_id' => null, 'slug' => 'pay-integraciones']);
        $integrations->fill([
            'parent_id'   => $root->id,
            'name'        => 'Integraciones',
            'icon'        => '🔌',
            'description' => 'Cómo conectar Bolivia Pay con tiendas y sistemas externos.',
            'is_active'   => true,
            'is_global'   => true,
        ]);
        $integrations->save();

        $path = plugins_path('aero/docs/content/pay/pay-plugin-wordpress.md');
        if (!is_file($path)) {
            return;
        }

        $article = Article::firstOrNew(['tenant_id' => null, 'slug' => 'pay-plugin-wordpress']);
        $article->fill([
            'category_id'    => $integrations->id,
            'title'          => 'Plugin de WordPress (WooCommerce)',
            'content'        => file_get_contents($path),
            'plugin_version' => $this->pluginVersion('aero/pay'),
            'sort_order'     => 0,
            'is_published'   => true,
            'is_global'      => true,
            'is_featured'    => false,
        ]);
        $article->save();
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
