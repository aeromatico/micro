<?php namespace Aero\Docs\Models;

use Aero\Docs\Classes\DocsScope;
use Aero\Docs\Classes\Markdown;
use Model;

class Article extends Model
{
    use \October\Rain\Database\Traits\Sluggable { newSluggableQuery as protected sluggableQueryBase;
    }
    use \Aero\Docs\Traits\InDocsScope;
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_docs_articles';

    public $fillable = [
        'tenant_id', 'is_global', 'category_id', 'title', 'slug', 'excerpt', 'content', 'plugin_version',
        'sort_order', 'is_published', 'is_featured', 'published_at',
    ];

    public $slugs = ['slug' => 'title'];

    public $rules = [
        'title' => 'required',
        'slug'  => 'required|alpha_dash',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'is_featured'  => 'boolean',
        'is_global'    => 'boolean',
    ];

    protected $dates = ['published_at'];

    public $jsonable = ['toc'];

    /** El contador de visitas arranca en 1000 para todo artículo nuevo. */
    public $attributes = [
        'views'       => 1000,
        'version'     => 1,
        'helpful_yes' => 0,
        'helpful_no'  => 0,
    ];

    public $belongsTo = [
        'category' => [Category::class, 'key' => 'category_id'],
    ];

    public $hasMany = [
        'versions' => [ArticleVersion::class, 'key' => 'article_id', 'order' => 'version desc'],
    ];

    /** Evita crear la instantánea cuando la versión no cambió. */
    protected bool $snapshotVersion = false;

    public function getCategoryIdOptions(): array
    {
        $options = [];
        foreach (Category::inScope($this->exists ? $this->tenant_id : DocsScope::currentTenantId())->orderBy('nest_left')->get() as $c) {
            $options[$c->id] = str_repeat('— ', (int) $c->nest_depth) . $c->name;
        }

        return $options;
    }

    public function beforeSave(): void
    {
        // Una categoría de otro ámbito no se puede colgar a mano.
        if ($this->category_id && !Category::inScope($this->tenant_id ? (int) $this->tenant_id : null)->whereKey($this->category_id)->exists()) {
            throw new \ApplicationException('La categoría no pertenece a este sitio.');
        }

        if ($this->isDirty('content') || !$this->content_html) {
            $out = Markdown::render($this->content);
            $this->content_html    = $out['html'];
            $this->toc             = $out['toc'];
            $this->reading_minutes = $out['minutes'];
        }
        if ($this->is_published && !$this->published_at) {
            $this->published_at = now();
        }
        if (!$this->excerpt && $this->content) {
            $this->excerpt = \Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($this->content_html))), 180);
        }

        // Toda actualización de un artículo existente genera la siguiente versión;
        // los artículos nuevos empiezan siempre en la versión 1.
        $this->version = $this->exists ? max(1, (int) $this->getOriginal('version')) + 1 : 1;
        $this->snapshotVersion = true;
    }

    /**
     * Guarda la instantánea de la versión recién creada. Se hace después de
     * persistir para tener el id del artículo y la versión definitiva.
     */
    public function afterSave(): void
    {
        if (!$this->snapshotVersion) {
            return;
        }
        $this->snapshotVersion = false;

        $this->versions()->create([
            'version'         => $this->version,
            'user_id'         => \BackendAuth::getUser()?->id,
            'title'           => $this->title,
            'excerpt'         => $this->excerpt,
            'content'         => $this->content,
            'content_html'    => $this->content_html,
            'toc'             => $this->toc,
            'reading_minutes' => $this->reading_minutes,
        ]);
    }

    /** «Nivel › Categoría › Título», recorriendo toda la cadena de padres. */
    public function getSelectLabelAttribute(): string
    {
        $parts = [];
        $cat = $this->category;
        for ($i = 0; $cat && $i < 10; $i++) {
            array_unshift($parts, $cat->name);
            $cat = $cat->parent_id ? Category::find($cat->parent_id) : null;
        }
        $parts[] = (string) $this->title;
        return implode(' › ', $parts);
    }

    /** Solo documentación de la plataforma (tenant_id NULL), para enlazarla desde otros plugins. */
    public function scopePlatform($query)
    {
        return $query->whereNull($this->getTable() . '.tenant_id');
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true)
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public function getUrlAttribute(): string
    {
        return url('documentacion/' . $this->slug);
    }
    /** El slug solo debe ser único dentro del ámbito del registro. */
    protected function newSluggableQuery()
    {
        return $this->sluggableQueryBase()->inScope($this->tenant_id ? (int) $this->tenant_id : null);
    }
}
