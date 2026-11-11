<?php

use October\Rain\Database\Updates\Seeder;

/**
 * Repite la reparación: el editor visual guarda `puck_data` como texto y se leía mal; las páginas con solo `puck_data` y el sitio publica `content` (HTML):
 * se genera el HTML de las que quedaron vacías.
 */
class RepairStringPageContent extends Seeder
{
    public function run()
    {
        if (!class_exists(\Aero\Sites\Models\Page::class) || !class_exists(\Aero\Sites\Classes\Ai\HeadlessRenderer::class) || !\Schema::hasTable('aero_sites_pages')) {
            return;
        }

        \Aero\Sites\Models\Page::where('content_mode', 'puck')->where(fn ($q) => $q->whereNull('content')->orWhere('content', ''))->get()->each(function ($page) {
            $html = \Aero\Workspaces\Classes\WebTools::puckHtml($page->puck_data);

            if ($html !== '') {
                $page->content = $html;
                $page->save();
            }
        });
    }
}
