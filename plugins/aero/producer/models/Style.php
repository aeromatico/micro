<?php namespace Aero\Producer\Models;

use Model;

/** Un estilo de video ya pulido por la plataforma: el tenant lo elige y pone su contenido. */
class Style extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_producer_styles';

    public $fillable = ['kind', 'slug', 'name', 'description', 'preview_url', 'max_seconds', 'credit_cost', 'is_active', 'sort_order'];

    public $rules = [
        'kind'        => 'required|in:presenter,explainer',
        'slug'        => 'required|alpha_dash|max:80',
        'name'        => 'required|max:190',
        'max_seconds' => 'required|integer|min:5|max:300',
        'credit_cost' => 'required|integer|min:0',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function beforeValidate(): void
    {
        $this->rules['slug'] = 'required|alpha_dash|max:80|unique:aero_producer_styles,slug,' . ($this->id ?: 'NULL');
    }

    public static function kinds(): array
    {
        return ['presenter' => 'Presentador', 'explainer' => 'Explicativo'];
    }

    public function getKindOptions(): array
    {
        return self::kinds();
    }

    public function getKindLabelAttribute(): string
    {
        return self::kinds()[$this->kind] ?? (string) $this->kind;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }
}
