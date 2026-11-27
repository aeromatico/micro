<?php

use Aero\Docs\Models\Article;
use October\Rain\Database\Updates\Seeder;

/**
 * Refresca desde su Markdown solo los artículos de Aero.Pay que explican la
 * comisión por cobro con QR. No toca título, categoría, estado de publicación
 * ni la versión documentada; si el artículo no existe, no lo crea.
 */
return new class extends Seeder
{
    public function run(): void
    {
        foreach (['pay-vista-general', 'pay-detalle-qr'] as $slug) {
            $path = plugins_path('aero/docs/content/pay/' . $slug . '.md');
            $article = Article::where('slug', $slug)->whereNull('tenant_id')->first();

            if (!$article || !is_file($path)) {
                continue;
            }

            $article->content = file_get_contents($path);
            $article->save();
        }
    }
};
