<?php namespace Aero\WpFlash\Models;

use Crypt;
use Illuminate\Contracts\Encryption\DecryptException;
use Model;
use Aero\Sites\Models\Tenant;
use Aero\Connector\Models\Connector;

/**
 * El childsite de WordPress Multisite provisionado para un tenant, y el
 * Connector WooCommerce (aero_connector_connectors) que se creó junto con él
 * para hablarle. La contraseña del admin generada se guarda cifrada (mismo
 * patrón que `Connector::credentials`) y solo se muestra en claro una vez,
 * justo después del provisioning — después solo se puede "revelar" desde el
 * backend detrás del permiso aero.wpflash.manage_sites, nunca queda en un
 * listado.
 */
class SiteInstance extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_wpflash_sites';

    public $fillable = [
        'tenant_id', 'wp_site_id', 'wp_admin_url', 'primary_domain', 'status',
        'admin_username', 'connector_id', 'last_synced_at', 'error_message',
    ];

    public const STATUSES = ['provisioning', 'active', 'error', 'suspended'];

    public $rules = [
        'tenant_id' => 'required|exists:aero_sites_tenants,id|unique:aero_wpflash_sites,tenant_id',
        'status'    => 'required|in:provisioning,active,error,suspended',
    ];

    protected $hidden = ['admin_password_encrypted'];

    public $attributes = [
        'status' => 'provisioning',
    ];

    public $dates = ['last_synced_at'];

    public $belongsTo = [
        'tenant'    => [Tenant::class],
        'connector' => [Connector::class],
    ];

    public function setAdminPasswordAttribute(?string $password): void
    {
        $this->attributes['admin_password_encrypted'] = $password
            ? Crypt::encryptString($password)
            : null;
    }

    public function getAdminPasswordAttribute(): ?string
    {
        if (!$this->admin_password_encrypted) {
            return null;
        }

        try {
            return Crypt::decryptString($this->admin_password_encrypted);
        }
        catch (DecryptException) {
            return null;
        }
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function getStatusLabelAttribute(): string
    {
        return trans("aero.wpflash::lang.site.status_{$this->status}");
    }
}
