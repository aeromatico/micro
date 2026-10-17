<?php namespace Aero\Rgpd\Models;

use Model;

class Config extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_rgpd_configs';

    public $fillable = [
        'tenant_id', 'is_enabled', 'title', 'message', 'policy_url', 'accept_label', 'reject_label',
        'customize_label', 'position', 'use_analytics', 'use_marketing', 'keep_log', 'version',
    ];

    public $rules = [
        'title'    => 'nullable|max:120',
        'position' => 'required|in:bottom,bottom-left,bottom-right,center',
        'policy_url' => ['nullable', 'max:500', 'regex:/^(https?:\/\/|\/)/i'],
    ];

    public $attributes = ['position' => 'bottom', 'version' => 1, 'use_analytics' => true, 'use_marketing' => true, 'keep_log' => true];

    protected $casts = [
        'is_enabled' => 'boolean', 'use_analytics' => 'boolean', 'use_marketing' => 'boolean', 'keep_log' => 'boolean',
    ];

    /** Configuración del alcance: tenant o plataforma (null). Sin fila = apagado. */
    public static function forScope(?int $tenantId): ?self
    {
        return static::where('tenant_id', $tenantId)->first();
    }

    /** La fila del alcance, creándola apagada (tenants) si aún no existe. */
    public static function ensureForScope(?int $tenantId): self
    {
        return static::forScope($tenantId) ?? static::create(['tenant_id' => $tenantId, 'is_enabled' => false]);
    }

    /** Alcance de un host: tenant resuelto por dominio, o plataforma (null). */
    public static function tenantIdForHost(string $host): ?int
    {
        static $sitesReady = null;
        $sitesReady ??= class_exists(\Aero\Sites\Models\Tenant::class) && \Schema::hasTable('aero_sites_domains');
        if (!$sitesReady) {
            return null;
        }

        return \Aero\Sites\Models\Tenant::resolveFromDomain($host)?->id;
    }

    public function getScopeLabelAttribute(): string
    {
        if (!$this->tenant_id) {
            return 'Plataforma';
        }
        if (class_exists(\Aero\Sites\Models\Tenant::class) && ($t = \Aero\Sites\Models\Tenant::find($this->tenant_id))) {
            return (string) $t->name;
        }

        return 'Tenant #' . $this->tenant_id;
    }

    public function getPositionOptions(): array
    {
        return ['bottom' => 'Barra inferior', 'bottom-left' => 'Esquina inferior izquierda', 'bottom-right' => 'Esquina inferior derecha', 'center' => 'Centro (modal)'];
    }

    public function beforeSave(): void
    {
        // Cambiar los textos o categorías cambia lo que se consintió: se vuelve a pedir.
        if ($this->exists && $this->isDirty(['message', 'title', 'use_analytics', 'use_marketing', 'policy_url']) && !$this->isDirty('version')) {
            $this->version = (int) $this->getOriginal('version') + 1;
        }
    }

    // Textos por defecto (RGPD: informar finalidad, igual peso aceptar/rechazar, revocable).
    public function textTitle(): string { return $this->title ?: 'Usamos cookies'; }

    public function textMessage(): string
    {
        return $this->message ?: 'Usamos cookies necesarias y, solo si aceptas, de análisis y marketing. Puedes cambiar tu decisión cuando quieras.';
    }

    public function textAccept(): string { return $this->accept_label ?: 'Aceptar todo'; }
    public function textReject(): string { return $this->reject_label ?: 'Rechazar'; }
    public function textCustomize(): string { return $this->customize_label ?: 'Personalizar'; }
}
