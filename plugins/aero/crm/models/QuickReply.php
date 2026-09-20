<?php namespace Aero\Crm\Models;

use Aero\Crm\Classes\TenantUsers;
use Model;

/**
 * Respuesta rápida: texto reutilizable que se invoca con /atajo (ej. /catalogo).
 * tenant_id NULL = plataforma; con valor = de ese tenant.
 */
class QuickReply extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_crm_quick_replies';

    public $fillable = ['tenant_id', 'area', 'title', 'shortcut', 'body', 'sort_order', 'is_active'];

    public $rules = [
        'tenant_id' => 'nullable|exists:aero_sites_tenants,id',
        'area'      => 'required|max:30',
        'title'     => 'required|max:255',
        'shortcut'  => ['required', 'max:40', 'regex:/^[a-z0-9_-]+$/'],
        'body'      => 'required',
        'sort_order' => 'nullable|integer|min:0',
    ];

    public $customMessages = [
        'shortcut.regex' => 'El atajo solo admite minúsculas, números, guion y guion bajo (sin "/" ni espacios).',
    ];

    public $attributes = ['is_active' => true, 'area' => 'general', 'sort_order' => 10, 'uses_count' => 0];

    public $belongsTo = [
        'tenant' => [\Aero\Sites\Models\Tenant::class],
    ];

    public static function areaOptions(): array
    {
        return [
            'general'    => 'General',
            'ventas'     => 'Ventas',
            'soporte'    => 'Soporte',
            'cobranzas'  => 'Cobranzas',
            'postventa'  => 'Postventa',
        ];
    }

    public function getAreaOptions(): array
    {
        return static::areaOptions();
    }

    /** "/Catalogo " → "catalogo": el usuario puede teclear la barra o mayúsculas. */
    public function setShortcutAttribute($value): void
    {
        $this->attributes['shortcut'] = ltrim(mb_strtolower(trim((string) $value)), '/');
    }

    public function getAreaLabelAttribute(): string
    {
        return static::areaOptions()[$this->area] ?? ucfirst((string) $this->area);
    }

    public function scopeInScope($query, ?int $tenantId)
    {
        return $tenantId ? $query->where('tenant_id', $tenantId) : $query->whereNull('tenant_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function beforeValidate(): void
    {
        $this->rules['shortcut'][] = \Illuminate\Validation\Rule::unique($this->table, 'shortcut')
            ->where(fn ($q) => $this->tenant_id ? $q->where('tenant_id', $this->tenant_id) : $q->whereNull('tenant_id'))
            ->ignore($this->id);
    }
}
