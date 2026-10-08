<?php namespace Aero\Finance\Models;

use Aero\Finance\Classes\TenantOwned;
use Model;

class FinanceSettings extends Model
{
    use TenantOwned;

    public $table = 'aero_finance_settings';

    public $fillable = ['tenant_id', 'auto_post', 'post_shop', 'post_gym'];

    protected $casts = ['auto_post' => 'boolean', 'post_shop' => 'boolean', 'post_gym' => 'boolean'];

    public static function forTenant(?int $tenantId): self
    {
        return self::firstOrCreate(['tenant_id' => $tenantId])->fresh() ?? new self(['tenant_id' => $tenantId]);
    }

    public static function allows(?int $tenantId, string $source): bool
    {
        if (!$tenantId) {
            return false;
        }
        $s = self::forTenant($tenantId);

        return $s->auto_post && (bool) ($s->{'post_' . $source} ?? false);
    }
}
