<?php namespace Aero\Docs\Components;

use Aero\Docs\Classes\DocsScope;
use Aero\Docs\Models\Article;
use Aero\Docs\Models\Guide;
use Cms\Classes\ComponentBase;

/**
 * Sitio público de guías interactivas (/guias).
 *
 * Uso: [guides] mode = "list|detail|article", slug = "{{ :slug }}"
 *   list    → todas las publicadas, agrupadas por plugin
 *   detail  → una guía por slug (404 si no está publicada)
 *   article → la guía publicada enlazada al artículo de docs con ese slug
 */
class Guides extends ComponentBase
{
    /** @var array<string, array<int, Guide>> */
    public array $byPlugin = [];
    public ?Guide $guide = null;
    public ?Article $article = null;

    public function componentDetails(): array
    {
        return ['name' => 'Guías', 'description' => 'Guías interactivas enlazadas a la documentación.'];
    }

    public function defineProperties(): array
    {
        return [
            'mode' => ['title' => 'Modo', 'type' => 'dropdown', 'default' => 'list',
                'options' => ['list' => 'Listado', 'detail' => 'Detalle', 'article' => 'Por artículo']],
            'slug' => ['title' => 'Slug', 'type' => 'string', 'default' => '{{ :slug }}'],
        ];
    }

    protected function published()
    {
        return Guide::published()->visibleIn(DocsScope::currentTenantId());
    }

    public function onRun()
    {
        switch ($this->property('mode')) {
            case 'detail':
                $this->guide = $this->published()->where('slug', $this->property('slug'))->first();
                if (!$this->guide) {
                    return $this->controller->run('404');
                }
                $this->article = $this->guide->article_id
                    ? Article::published()->visibleIn(DocsScope::currentTenantId())->find($this->guide->article_id)
                    : null;
                $this->page->title = $this->guide->title . ' — Guías interactivas';
                break;

            case 'article':
                $articleId = Article::published()->visibleIn(DocsScope::currentTenantId())
                    ->where('slug', $this->property('slug'))->value('id');
                $this->guide = $articleId ? $this->published()->where('article_id', $articleId)->first() : null;
                break;

            default:
                foreach ($this->published()->orderBy('plugin')->orderBy('sort_order')->orderBy('title')->get() as $g) {
                    $this->byPlugin[$g->plugin][] = $g;
                }
        }
    }
}
