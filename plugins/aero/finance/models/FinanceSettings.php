<?php namespace Aero\Finance\Models;

use Aero\Finance\Classes\TenantOwned;
use Model;

class FinanceSettings extends Model
{
    use TenantOwned;

    public $table = 'aero_finance_settings';

    public $fillable = ['tenant_id', 'enabled', 'auto_post', 'post_shop', 'post_gym', 'post_portal'];

    protected $casts = ['enabled' => 'boolean', 'auto_post' => 'boolean', 'post_shop' => 'boolean', 'post_gym' => 'boolean', 'post_portal' => 'boolean'];

    public static function forTenant(?int $tenantId): self
    {
        return self::firstOrCreate(['tenant_id' => $tenantId])->fresh() ?? new self(['tenant_id' => $tenantId]);
    }

    /** Sin fila de configuración, Finanzas está activo. */
    public static function isEnabled(?int $tenantId): bool
    {
        return (bool) (static::where('tenant_id', $tenantId)->value('enabled') ?? true);
    }

    public static function allows(?int $tenantId, string $source): bool
    {
        if (!$tenantId) {
            return false;
        }
        $s = self::forTenant($tenantId);

        return $s->enabled && $s->auto_post && (bool) ($s->{'post_' . $source} ?? false);
    }
}
