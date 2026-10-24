<?php namespace Aero\Producer\Models;

use Crypt;
use Model;

/**
 * Llave de un proveedor. tenant_id NULL = la de la plataforma.
 * La llave se guarda cifrada y nunca se vuelve a mostrar completa.
 */
class Credential extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_producer_credentials';

    public $fillable = ['tenant_id', 'provider', 'label', 'api_key', 'is_active'];

    public $hidden = ['api_key_enc'];

    public $rules = [
        'label' => 'required|max:120',
    ];

    protected $casts = ['is_active' => 'boolean', 'last_checked_at' => 'datetime'];

    public $dates = ['last_checked_at'];

    /** Vacío = conservar la actual. */
    public function setApiKeyAttribute($value): void
    {
        $value = trim((string) $value);
        if ($value !== '') {
            $this->attributes['api_key_enc'] = Crypt::encryptString($value);
            $this->attributes['last_status'] = null;
        }
    }

    public function getApiKeyAttribute(): string
    {
        return '';
    }

    public function plainKey(): ?string
    {
        if (!$this->api_key_enc) {
            return null;
        }

        try {
            return Crypt::decryptString($this->api_key_enc);
        } catch (\Throwable) {
            return null;
        }
    }

    public function getMaskedKeyAttribute(): string
    {
        $key = $this->plainKey();

        return $key ? substr($key, 0, 4) . '…' . substr($key, -3) : '—';
    }

    public function getProviderOptions(): array
    {
        return ['elevenlabs' => 'ElevenLabs (voz)', 'heygen' => 'HeyGen (presentador)'];
    }

    public function getScopeLabelAttribute(): string
    {
        return $this->tenant_id ? 'Propia' : 'Plataforma';
    }

    public function beforeValidate(): void
    {
        if (!$this->exists && !$this->api_key_enc) {
            $this->rules['api_key'] = 'required';
        }
    }
}
