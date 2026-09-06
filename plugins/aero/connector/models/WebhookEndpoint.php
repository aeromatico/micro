<?php namespace Aero\Connector\Models;

use Crypt;
use Illuminate\Contracts\Encryption\DecryptException;
use Model;
use Str;

/**
 * Un punto de recepción de webhooks de terceros. La URL pública que se le da
 * al proveedor es `{app_url}/connector/webhooks/{slug}` — ver
 * Aero\Connector\Http\Controllers\Api\WebhookReceiverController.
 */
class WebhookEndpoint extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_connector_webhook_endpoints';

    public $fillable = [
        'name', 'slug', 'type', 'verification', 'signature_header', 'dispatch_event', 'is_enabled',
    ];

    public $rules = [
        'name'           => 'required',
        'slug'           => 'required|alpha_dash|unique:aero_connector_webhook_endpoints',
        'verification'   => 'required|in:none,hmac_sha256,header_token',
        'dispatch_event' => 'required',
    ];

    protected $hidden = ['secret_encrypted'];

    public $attributes = [
        'is_enabled'   => true,
        'verification' => 'none',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    public $hasMany = [
        'logs' => [\Aero\Connector\Models\ConnectorLog::class, 'key' => 'webhook_endpoint_id', 'order' => 'id desc'],
    ];

    public function beforeValidate()
    {
        if (!$this->slug && $this->name) {
            $this->slug = Str::slug($this->name);
        }
    }

    public function setSecretAttribute(?string $secret): void
    {
        $this->attributes['secret_encrypted'] = $secret ? Crypt::encryptString($secret) : null;
    }

    public function getSecretAttribute(): ?string
    {
        if (!$this->secret_encrypted) {
            return null;
        }

        try {
            return Crypt::decryptString($this->secret_encrypted);
        }
        catch (DecryptException) {
            return null;
        }
    }

    public function getPublicUrlAttribute(): string
    {
        return url('connector/webhooks/' . $this->slug);
    }

    public function getVerificationOptions(): array
    {
        return [
            'none'         => 'Ninguna (no recomendado)',
            'hmac_sha256'  => 'Firma HMAC-SHA256',
            'header_token' => 'Token fijo en header',
        ];
    }
}
