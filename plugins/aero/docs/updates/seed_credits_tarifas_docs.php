<?php

use Aero\Docs\Models\Article;
use Aero\Docs\Models\Category;
use October\Rain\Database\Updates\Seeder;

/**
 * Agrega "Tarifas: ¿dónde y cuánto te cobramos?" y refresca el contenido de
 * los artículos existentes de Aero.Credits (corrige "azul/rojo" por los
 * colores reales bronce/plata/oro — ver credits-tarifas.md).
 */
return new class extends Seeder
{
    public function run(): void
    {
        $forms = Category::where('slug', 'credits-funciones')->first();

        if (!$forms) {
            return;
        }

        $dir = plugins_path('aero/docs/content/credits');
        $articles = [
            ['file' => 'credits-vista-general', 'title' => 'Vista general', 'sort' => 0, 'featured' => true],
            ['file' => 'credits-tarifas', 'title' => 'Tarifas: ¿dónde y cuánto te cobramos?', 'sort' => 5, 'featured' => true],
            ['file' => 'credits-wallet', 'title' => 'Wallet (Mis monedas)', 'sort' => 10, 'featured' => false],
        ];

        foreach ($articles as $item) {
            $path = $dir . '/' . $item['file'] . '.md';
            if (!is_file($path)) {
                continue;
            }

            $article = Article::firstOrNew(['slug' => $item['file']]);
            $article->fill([
                'category_id'    => $forms->id,
                'title'          => $item['title'],
                'content'        => file_get_contents($path),
                'plugin_version' => $this->pluginVersion('aero/credits'),
                'sort_order'     => $item['sort'],
                'is_published'   => true,
                'is_featured'    => (bool) ($item['featured'] ?? false),
                'tenant_id'      => null,
                'is_global'      => true,
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
