<?php namespace Aero\Sites\Models;

use Model;

class Page extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;
    use \October\Rain\Database\Traits\Sortable;

    public $table = 'aero_sites_pages';

    public $fillable = [
        'tenant_id', 'title', 'slug', 'content', 'content_mode', 'puck_data', 'is_placeholder',
        'meta_title', 'meta_description', 'layout',
        'is_published', 'show_in_menu', 'menu_positions', 'sort_order',
    ];

    /**
     * Posiciones de menú que el tema puede pintar. Cada tema decide dónde
     * renderiza cada una: el tema microsites usa navbar (menú de escritorio),
     * sidebar (menú móvil) y footer; top aún no tiene lugar en ese diseño.
     */
    public const MENU_POSITIONS = [
        'top'     => 'Barra superior',
        'navbar'  => 'Menú principal (escritorio)',
        'sidebar' => 'Menú lateral (móvil)',
        'footer'  => 'Pie de página',
    ];

    protected $jsonable = ['puck_data', 'menu_positions'];

    protected $casts = [
        'is_published' => 'boolean',
        'show_in_menu' => 'boolean',
    ];

    protected $dates = ['deleted_at'];

    public $rules = [
        'tenant_id' => 'required|exists:aero_sites_tenants,id',
        'title'     => 'required|min:2|max:200',
        'slug'      => 'nullable|alpha_dash|max:200',
        'layout'    => 'required',
    ];

    public $belongsTo = [
        'tenant' => [Tenant::class],
    ];

    public $attachOne = [
        'og_image' => \System\Models\File::class,
    ];

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function getMenuPositionsOptions(): array
    {
        return self::MENU_POSITIONS;
    }

    /** La página aparece en la posición de menú dada (si está marcada para menús). */
    public function isInMenu(string $position): bool
    {
        return $this->show_in_menu && in_array($position, (array) $this->menu_positions, true);
    }

    public function getEffectiveMetaTitleAttribute(): string
    {
        return $this->meta_title ?: $this->title;
    }
}
