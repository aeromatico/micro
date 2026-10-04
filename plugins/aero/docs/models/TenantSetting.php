<?php namespace Aero\Docs\Models;

use Model;

/**
 * Interruptor de la documentación de cada tenant. La plataforma (tenant_id NULL)
 * es el portal y siempre está disponible; un tenant solo la tiene si la activó.
 */
class TenantSetting extends Model
{
    public $table = 'aero_docs_tenant_settings';

    protected $fillable = ['tenant_id', 'enabled'];

    protected $casts = ['enabled' => 'boolean'];

    public static function enabledFor(?int $tenantId): bool
    {
        if (!$tenantId) {
            return true;
        }

        return (bool) static::where('tenant_id', $tenantId)->value('enabled');
    }
}
