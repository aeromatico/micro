<?php namespace Aero\Docs\Models;

use Aero\Docs\Classes\Markdown;
use Model;

class Article extends Model
{
    use \October\Rain\Database\Traits\Sluggable;
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_docs_articles';

    public $fillable = [
        'category_id', 'title', 'slug', 'excerpt', 'content',
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
    ];

    protected $dates = ['published_at'];

    public $jsonable = ['toc'];

    public $belongsTo = [
        'category' => [Category::class, 'key' => 'category_id'],
    ];

    public function getCategoryIdOptions(): array
    {
        $options = [];
        foreach (Category::orderBy('nest_left')->get() as $c) {
            $options[$c->id] = str_repeat('— ', (int) $c->nest_depth) . $c->name;
        }

        return $options;
    }

    public function beforeSave(): void
    {
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
}
