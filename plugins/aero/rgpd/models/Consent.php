<?php namespace Aero\Rgpd\Models;

use Model;

/** Prueba de consentimiento (art. 7.1 RGPD). Solo se escribe desde el endpoint público. */
class Consent extends Model
{
    public $table = 'aero_rgpd_consents';

    public $fillable = ['tenant_id', 'consent_id', 'analytics', 'marketing', 'config_version', 'ip_hash', 'user_agent'];

    protected $casts = ['analytics' => 'boolean', 'marketing' => 'boolean'];

    public function getScopeLabelAttribute(): string
    {
        return $this->tenant_id ? 'Tenant #' . $this->tenant_id : 'Plataforma';
    }
}
