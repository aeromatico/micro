<?php namespace Aero\Sms\Models;

use Model;

class Template extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_sms_templates';

    public $fillable = ['tenant_id', 'name', 'slug', 'body', 'is_active'];

    public $rules = [
        'name' => 'required',
        'slug' => 'required|alpha_dash',
        'body' => 'required',
    ];

    /** El slug es único por tenant (las globales, tenant_id NULL, forman su propio grupo). */
    public function beforeValidate(): void
    {
        $this->rules['slug'] = 'required|alpha_dash|unique:aero_sms_templates,slug,'
            . ($this->id ?: 'NULL') . ',id,tenant_id,' . ($this->tenant_id ?: 'NULL');
    }

    public function getScopeLabelAttribute(): string
    {
        return $this->tenant_id ? 'Propia' : 'Global';
    }

    /** La del propio tenant gana sobre una global con el mismo código. */
    public static function findForTenant(string $slug, ?int $tenantId): ?self
    {
        return static::where('slug', $slug)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('tenant_id')->when($tenantId, fn ($q) => $q->orWhere('tenant_id', $tenantId)))
            ->orderByRaw('tenant_id IS NULL')
            ->first();
    }

    protected $casts = ['is_active' => 'boolean'];

    /** Reemplaza {{variable}} por su valor; las que no vienen se dejan vacías. */
    public static function render(string $body, array $vars = []): string
    {
        return preg_replace_callback('/\{\{\s*([\w.]+)\s*\}\}/', function ($m) use ($vars) {
            $value = $vars[$m[1]] ?? '';

            return is_scalar($value) ? (string) $value : '';
        }, $body);
    }
}
