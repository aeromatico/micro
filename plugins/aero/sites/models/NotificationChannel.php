<?php namespace Aero\Sites\Models;

use Crypt;
use Model;

class NotificationChannel extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Sortable;

    public $table = 'aero_sites_notification_channels';

    public $fillable = [
        'tenant_id', 'type', 'label', 'config', 'is_enabled', 'sort_order',
    ];

    public $rules = [
        'tenant_id' => 'required|exists:aero_sites_tenants,id',
        'type'      => 'required',
        'label'     => 'required',
    ];

    public $belongsTo = [
        'tenant' => [Tenant::class],
    ];

    public function getConfigAttribute($value): array
    {
        if (!$value) return [];
        try {
            return json_decode(Crypt::decryptString($value), true) ?? [];
        } catch (\Exception) {
            return [];
        }
    }

    public function setConfigAttribute(array $value): void
    {
        $this->attributes['config'] = Crypt::encryptString(json_encode($value));
    }

    public function getTypeOptions(): array
    {
        return [
            'email'    => 'Email',
            'whatsapp' => 'WhatsApp',
            'telegram' => 'Telegram',
            'sms'      => 'SMS',
        ];
    }

    /**
     * Cuentas de WhatsApp conectadas en Aero.Hello para este tenant.
     * Dependencia blanda: sin Aero.Hello instalado el dropdown queda vacío.
     */
    public function getHelloAccountOptions(): array
    {
        if (!class_exists(\Aero\Hello\Models\Account::class) || !$this->tenant_id) {
            return [];
        }

        return \Aero\Hello\Models\Account::enabled()
            ->ofPlatform('whatsapp')
            ->whereHas('profile', fn ($query) => $query->where('tenant_id', $this->tenant_id))
            ->pluck('label', 'id')
            ->all();
    }

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
