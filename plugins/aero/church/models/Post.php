<?php namespace Aero\Church\Models;

use Aero\Church\Classes\Slugs;
use Model;

/** Noticia o comunicado publicado en el sitio (público) o en el portal del miembro. */
class Post extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \Aero\Church\Classes\TenantOwned;

    public $table = 'aero_church_posts';

    public $attributes = ['kind' => 'news', 'visibility' => 'public', 'is_published' => false, 'is_pinned' => false];

    public $fillable = ['tenant_id', 'title', 'body', 'kind', 'visibility', 'is_published', 'is_pinned', 'published_at'];

    public $nullable = ['published_at'];

    public $rules = [
        'title'      => 'required|string|max:255',
        'body'       => 'required|string',
        'kind'       => 'required|in:news,notice',
        'visibility' => 'required|in:public,members',
    ];

    public $dates = ['published_at'];

    public function beforeSave(): void
    {
        if (!$this->slug) {
            $this->slug = Slugs::unique(self::class, $this->tenant_id, $this->title, $this->id);
        }
        if ($this->is_published && !$this->published_at) {
            $this->published_at = now();
        }
    }

    public function scopeShown($query, bool $isMember = false)
    {
        $query->where('is_published', true)->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));

        return $isMember ? $query : $query->where('visibility', 'public');
    }

    public function getKindOptions(): array
    {
        return ['news' => 'Noticia', 'notice' => 'Comunicado'];
    }

    public function getVisibilityOptions(): array
    {
        return ['public' => 'Público', 'members' => 'Solo miembros'];
    }
}
