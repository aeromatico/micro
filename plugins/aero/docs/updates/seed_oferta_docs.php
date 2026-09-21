<?php

use Aero\Docs\Models\Article;
use Aero\Docs\Models\Category;
use October\Rain\Database\Updates\Seeder;

return new class extends Seeder
{
    /**
     * Documento general de la oferta: funcionalidades de cada plugin.
     * Se mantiene como fuente para la descripción comercial de la plataforma.
     */
    public function run(): void
    {
        $offer = Category::firstOrNew(['tenant_id' => null, 'slug' => 'oferta']);
        $offer->fill([
            'name'        => 'Oferta',
            'icon'        => '🧩',
            'description' => 'Funcionalidades y valor de cada plugin de la plataforma.',
            'is_active'   => true,
            'is_global'   => true,
        ]);
        $offer->save();

        $path = plugins_path('aero/docs/content/plugins/funcionalidades.md');
        if (!is_file($path)) {
            return;
        }

        $article = Article::firstOrNew(['tenant_id' => null, 'slug' => 'oferta-funcionalidades']);
        $article->fill([
            'category_id'  => $offer->id,
            'title'        => 'Funcionalidades por plugin',
            'content'      => file_get_contents($path),
            'sort_order'   => 0,
            'is_published' => true,
                'is_global'    => true,
            'is_featured'  => true,
        ]);
        $article->save();
    }
};
