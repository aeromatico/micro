<?php namespace Aero\Docs\Models;

use Model;

class Category extends Model
{
    use \October\Rain\Database\Traits\NestedTree;
    use \October\Rain\Database\Traits\Nullable;
    use \October\Rain\Database\Traits\Sluggable { newSluggableQuery as protected sluggableQueryBase;
    }
    use \Aero\Docs\Traits\InDocsScope;
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_docs_categories';

    public $fillable = ['tenant_id', 'is_global', 'parent_id', 'name', 'slug', 'icon', 'description', 'is_active'];

    public $nullable = ['parent_id', 'icon', 'description'];

    public $slugs = ['slug' => 'name'];

    public $rules = [
        'name' => 'required',
        'slug' => 'required|alpha_dash',
    ];

    protected $casts = ['is_active' => 'boolean', 'is_global' => 'boolean'];

    public $hasMany = [
        'articles' => [Article::class, 'key' => 'category_id'],
    ];

    /** Opciones del selector de padre: árbol indentado, sin la propia rama. */
    public function getParentIdOptions(): array
    {
        $options = [];
        foreach (static::inScope($this->exists ? $this->tenant_id : \Aero\Docs\Classes\DocsScope::currentTenantId())->orderBy('nest_left')->get() as $c) {
            if ($this->exists && $c->nest_left >= $this->nest_left && $c->nest_right <= $this->nest_right) {
                continue;
            }
            $options[$c->id] = str_repeat('— ', (int) $c->nest_depth) . $c->name;
        }

        return $options;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function beforeSave(): void
    {
        // El padre debe ser del mismo ámbito (el árbol es global, los sitios no).
        if ($this->parent_id && !static::inScope($this->tenant_id ? (int) $this->tenant_id : null)->whereKey($this->parent_id)->exists()) {
            throw new \ApplicationException('La categoría padre no pertenece a este sitio.');
        }
    }

    public function beforeDelete(): void
    {
        // Los artículos de la categoría (y sus descendientes) quedan sin categoría.
        Article::whereIn('category_id', $this->getAllChildren()->pluck('id')->push($this->id))
            ->update(['category_id' => null]);
    }

    /** El slug solo debe ser único dentro del ámbito del registro. */
    protected function newSluggableQuery()
    {
        return $this->sluggableQueryBase()->inScope($this->tenant_id ? (int) $this->tenant_id : null);
    }
}
