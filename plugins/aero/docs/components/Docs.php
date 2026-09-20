<?php namespace Aero\Docs\Components;

use Aero\Docs\Models\Article;
use Aero\Docs\Models\Category;
use Cms\Classes\ComponentBase;

/**
 * Sirve el sitio público de documentación. Una sola pasada arma el árbol
 * (categorías anidadas + artículos) y de ahí salen sidebar, migas,
 * anterior/siguiente y la rama abierta.
 *
 * Uso: [docs] mode = "home|category|article", slug = "{{ :slug }}"
 */
class Docs extends ComponentBase
{
    public array $tree = [];
    public array $openIds = [];
    public array $breadcrumbs = [];
    public array $results = [];
    public string $q = '';

    public ?array $category = null;
    public ?Article $article = null;
    public ?array $prev = null;
    public ?array $next = null;
    public array $featured = [];

    /** Mapa id => nodo por referencia, para subir por ancestros. */
    protected array $nodes = [];
    /** Artículos en orden de lectura (profundidad primero). */
    protected array $flat = [];

    public function componentDetails(): array
    {
        return ['name' => 'Docs', 'description' => 'Sitio público de documentación.'];
    }

    public function defineProperties(): array
    {
        return [
            'mode' => ['title' => 'Modo', 'type' => 'dropdown', 'default' => 'home',
                'options' => ['home' => 'Portada', 'category' => 'Categoría', 'article' => 'Artículo']],
            'slug' => ['title' => 'Slug', 'type' => 'string', 'default' => '{{ :slug }}'],
        ];
    }

    public function onRun()
    {
        $this->buildTree();
        $this->q = trim((string) request()->query('q', ''));

        switch ($this->property('mode')) {
            case 'article':
                return $this->loadArticle();
            case 'category':
                return $this->loadCategory();
            default:
                return $this->loadHome();
        }
    }

    protected function buildTree(): void
    {
        $cats = Category::active()->orderBy('nest_left')->get();
        $arts = Article::published()->orderBy('sort_order')->orderBy('title')
            ->get(['id', 'category_id', 'title', 'slug', 'excerpt', 'is_featured', 'reading_minutes'])
            ->groupBy('category_id');

        foreach ($cats as $c) {
            $this->nodes[$c->id] = [
                'id' => $c->id, 'parent_id' => $c->parent_id, 'name' => $c->name, 'slug' => $c->slug,
                'icon' => $c->icon, 'description' => $c->description,
                'url' => url('documentacion/categoria/' . $c->slug), 'depth' => (int) $c->nest_depth,
                'articles' => ($arts[$c->id] ?? collect())->map(fn ($a) => $this->articleRow($a, $c->id))->all(),
                'children' => [], 'count' => 0,
            ];
        }
        // Una categoría cuyo padre está inactivo (o no existe) no aparece.
        foreach ($this->nodes as $id => &$n) {
            if ($n['parent_id'] && isset($this->nodes[$n['parent_id']])) {
                $this->nodes[$n['parent_id']]['children'][] = &$n;
            }
        }
        unset($n);

        $roots = [];
        foreach ($this->nodes as $id => &$n) {
            if (!$n['parent_id']) {
                $roots[] = &$n;
            }
        }
        unset($n);

        foreach ($roots as &$r) {
            $this->finalize($r);
        }
        unset($r);

        // Artículos sin categoría: primero en el orden de lectura, no en el sidebar.
        $orphans = ($arts[''] ?? collect())->map(fn ($a) => $this->articleRow($a, null))->all();
        $this->flat = array_merge($orphans, $this->flat);
        $this->tree = $roots;
    }

    /** Cuenta artículos del subárbol, descarta ramas vacías y llena el orden de lectura. */
    protected function finalize(array &$node): int
    {
        foreach ($node['articles'] as $a) {
            $this->flat[] = $a;
        }
        $count = count($node['articles']);
        $kept = [];
        foreach ($node['children'] as &$child) {
            if ($this->finalize($child) > 0) {
                $kept[] = &$child;
            }
        }
        unset($child);
        $node['children'] = $kept;
        $node['count'] = $count + array_sum(array_column($kept, 'count'));

        return $node['count'];
    }

    protected function articleRow($a, ?int $catId): array
    {
        return [
            'id' => $a->id, 'title' => $a->title, 'slug' => $a->slug, 'excerpt' => $a->excerpt,
            'url' => url('documentacion/' . $a->slug), 'category_id' => $catId,
            'featured' => (bool) $a->is_featured, 'minutes' => $a->reading_minutes,
        ];
    }

    protected function ancestry(?int $categoryId): array
    {
        $chain = [];
        while ($categoryId && isset($this->nodes[$categoryId])) {
            array_unshift($chain, $this->nodes[$categoryId]);
            $categoryId = $this->nodes[$categoryId]['parent_id'];
        }

        return $chain;
    }

    protected function crumbs(array $chain): array
    {
        $out = [['name' => 'Documentación', 'url' => url('documentacion')]];
        foreach ($chain as $n) {
            $out[] = ['name' => $n['name'], 'url' => $n['url']];
        }

        return $out;
    }

    protected function loadHome()
    {
        $this->breadcrumbs = [];
        $this->featured = array_slice(array_values(array_filter($this->flat, fn ($a) => $a['featured'])), 0, 6);

        if ($this->q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $this->q) . '%';
            $this->results = Article::published()
                ->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('excerpt', 'like', $like)->orWhere('content', 'like', $like))
                ->orderByRaw('title like ? desc', [$like])->limit(30)
                ->get(['id', 'title', 'slug', 'excerpt', 'category_id'])
                ->map(fn ($a) => $this->articleRow($a, $a->category_id) + [
                    'path' => implode(' › ', array_column($this->ancestry($a->category_id), 'name')),
                ])->all();
        }
    }

    protected function loadCategory()
    {
        $id = null;
        foreach ($this->nodes as $n) {
            if ($n['slug'] === $this->property('slug')) {
                $id = $n['id'];
            }
        }
        if (!$id) {
            return $this->controller->run('404');
        }
        $this->category = $this->nodes[$id];
        $chain = $this->ancestry($id);
        $this->openIds = array_column($chain, 'id');
        $this->breadcrumbs = $this->crumbs($chain);
    }

    protected function loadArticle()
    {
        $article = Article::published()->where('slug', $this->property('slug'))->first();
        if (!$article) {
            return $this->controller->run('404');
        }
        Article::where('id', $article->id)->increment('views');

        $this->article = $article;
        $chain = $this->ancestry($article->category_id);
        $this->openIds = array_column($chain, 'id');
        $this->category = $chain ? end($chain) : null;
        $this->breadcrumbs = $this->crumbs($chain);

        foreach ($this->flat as $i => $a) {
            if ($a['id'] === $article->id) {
                $this->prev = $this->flat[$i - 1] ?? null;
                $this->next = $this->flat[$i + 1] ?? null;
            }
        }
        $this->page->title = $article->title . ' — Documentación';
        $this->page->description = $article->excerpt;
    }
}
