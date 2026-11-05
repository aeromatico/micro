<?php namespace Aero\Tickets\Models;

use Model;

/** Categoría de evento. Catálogo global de la plataforma (sin tenant). */
class Category extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_tickets_categories';

    public $fillable = ['name', 'slug', 'icon', 'sort_order', 'is_active'];

    public $rules = [
        'name' => 'required|string|max:255',
        'slug' => 'required|string|max:255|unique:aero_tickets_categories,slug',
    ];

    public $hasMany = ['events' => [Event::class, 'key' => 'category_id']];

    public function beforeValidate(): void
    {
        if (empty($this->slug) && $this->name) {
            $this->slug = \Str::slug($this->name);
        }
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }
}
