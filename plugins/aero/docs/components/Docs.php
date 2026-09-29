<?php namespace Aero\Docs\Components;

use Aero\Docs\Models\Article;
use Aero\Docs\Models\Category;
use Aero\Docs\Models\Guide;
use Cms\Classes\ComponentBase;
use Illuminate\Support\Facades\RateLimiter;

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
    public array $versions = [];

    /** Guías interactivas publicadas visibles en este sitio: alimenta el acceso del menú lateral. */
    public int $guidesCount = 0;

    /** Estado del bloque "¿te resultó útil?". */
    public int $helpfulYes = 0;
    public int $helpfulNo = 0;
    public bool $hasVoted = false;
    public ?string $userVote = null;

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
            'base' => ['title' => 'Ruta base', 'type' => 'string', 'default' => 'documentacion',
                'description' => 'Prefijo de las URLs (ej. "documentacion" o "ayuda").'],
        ];
    }

    /** Ámbito del sitio que atiende la petición: un tenant o la plataforma (null). */
    protected function tenantId(): ?int
    {
        return \Aero\Docs\Classes\DocsScope::currentTenantId();
    }

    /** Prefijo de las URLs del centro de ayuda. */
    protected function base(): string
    {
        return trim((string) $this->property('base'), '/') ?: 'documentacion';
    }

    public function onRun()
    {
        $this->buildTree();
        $this->guidesCount = Guide::published()->visibleIn($this->tenantId())->count();
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
        $cats = $this->resolveTreeRoots(
            Category::visibleIn($this->tenantId())->active()->orderBy('nest_left')->get()
        );
        $arts = Article::visibleIn($this->tenantId())->published()->orderBy('sort_order')->orderBy('title')
            ->get(['id', 'category_id', 'title', 'slug', 'excerpt', 'is_featured', 'reading_minutes'])
            ->groupBy('category_id');

        foreach ($cats as $c) {
            $this->nodes[$c->id] = [
                'id' => $c->id, 'parent_id' => $c->parent_id, 'name' => $c->name, 'slug' => $c->slug,
                'icon' => $c->icon, 'description' => $c->description, 'tenant_id' => $c->tenant_id,
                'url' => url($this->base() . '/categoria/' . $c->slug), 'depth' => (int) $c->nest_depth,
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

    /**
     * Una categoría global puede colgar de un padre que no es global y que, por
     * tanto, no se muestra en este sitio. En vez de exponer ese padre (de otro
     * ámbito) o perder la categoría, la promovemos a raíz del árbol público.
     */
    protected function resolveTreeRoots($cats)
    {
        $ids = array_map('intval', $cats->pluck('id')->all());

        foreach ($cats as $c) {
            if ($c->parent_id && !in_array((int) $c->parent_id, $ids, true)) {
                $c->parent_id = null;
            }
        }

        return $cats->sortBy('nest_left')->values();
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
            'url' => url($this->base() . '/' . $a->slug), 'category_id' => $catId,
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
        $out = [['name' => 'Documentación', 'url' => url($this->base())]];
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
            $this->results = Article::visibleIn($this->tenantId())->published()
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
        // Si un slug existe en el tenant y también como global, gana el propio.
        $id = null;
        $fallback = null;
        foreach ($this->nodes as $n) {
            if ($n['slug'] !== $this->property('slug')) {
                continue;
            }
            if ($n['tenant_id'] !== null) {
                $id = $n['id'];
            }
            elseif ($fallback === null) {
                $fallback = $n['id'];
            }
        }
        $id = $id ?: $fallback;

        if (!$id) {
            return $this->controller->run('404');
        }
        $this->category = $this->nodes[$id];
        $chain = $this->ancestry($id);
        $this->openIds = array_column($chain, 'id');
        $this->breadcrumbs = $this->crumbs($chain);

        $this->page->title = $this->category['name'] . ' — Documentación — Market';
        if ($this->category['description']) {
            $this->page->description = $this->category['description'];
        }
    }

    protected function loadArticle()
    {
        // Ante un slug compartido con un artículo global, gana el del tenant.
        $article = Article::visibleIn($this->tenantId())->published()
            ->where('slug', $this->property('slug'))
            ->orderByRaw('tenant_id is null')
            ->first();
        if (!$article) {
            return $this->controller->run('404');
        }

        // El contador arranca en 1000 y suma una visita por cada carga.
        Article::where('id', $article->id)->increment('views');
        $article->refresh();

        $this->article = $article;
        $chain = $this->ancestry($article->category_id);
        $this->openIds = array_column($chain, 'id');
        $this->category = $chain ? end($chain) : null;
        $this->breadcrumbs = $this->crumbs($chain);

        $this->helpfulYes = (int) $article->helpful_yes;
        $this->helpfulNo = (int) $article->helpful_no;

        $votes = (array) session()->get('aero_docs_feedback', []);
        if (isset($votes[$article->id])) {
            $this->hasVoted = true;
            $this->userVote = $votes[$article->id];
        }

        $this->versions = $article->versions()
            ->orderByDesc('version')
            ->get(['version', 'created_at'])
            ->map(fn ($v) => ['version' => (int) $v->version, 'date' => $v->created_at])
            ->all();

        foreach ($this->flat as $i => $a) {
            if ($a['id'] === $article->id) {
                $this->prev = $this->flat[$i - 1] ?? null;
                $this->next = $this->flat[$i + 1] ?? null;
            }
        }
        $this->page->title = $article->title . ' — Documentación';
        $this->page->description = $article->excerpt;
    }

    /**
     * Registra el voto "¿te resultó útil?" una sola vez por sesión y artículo,
     * con límite de intentos por IP.
     */
    public function onHelpful(): array
    {
        $article = Article::visibleIn($this->tenantId())->published()->find((int) post('id'));
        if (!$article) {
            return ['#docs-feedback' => '<div id="docs-feedback" class="mt-10 text-sm text-ink-dim">Artículo no encontrado.</div>'];
        }

        $this->helpfulYes = (int) $article->helpful_yes;
        $this->helpfulNo = (int) $article->helpful_no;

        $votes = (array) session()->get('aero_docs_feedback', []);
        $this->userVote = $votes[$article->id] ?? null;
        $this->hasVoted = $this->userVote !== null;

        if (!$this->hasVoted) {
            $key = 'aero_docs_feedback:' . request()->ip() . ':' . $article->id;
            if (RateLimiter::tooManyAttempts($key, 5)) {
                return ['#docs-feedback' => '<div id="docs-feedback" class="mt-10 text-sm text-ink-dim">Demasiados intentos. Intenta de nuevo más tarde.</div>'];
            }
            RateLimiter::hit($key, 86400);

            $this->userVote = post('vote') === 'no' ? 'no' : 'yes';
            $this->hasVoted = true;

            Article::where('id', $article->id)
                ->increment($this->userVote === 'yes' ? 'helpful_yes' : 'helpful_no');

            $votes[$article->id] = $this->userVote;
            session()->put('aero_docs_feedback', $votes);

            $article->refresh();
            $this->helpfulYes = (int) $article->helpful_yes;
            $this->helpfulNo = (int) $article->helpful_no;
        }

        $this->article = $article;

        return ['#docs-feedback' => $this->renderPartial('@feedback')];
    }
}
