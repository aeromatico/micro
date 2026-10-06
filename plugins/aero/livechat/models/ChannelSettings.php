<?php namespace Aero\Livechat\Models;

use Aero\Hello\Models\Account;
use Model;

/**
 * Puente de Livechat a WhatsApp, por tenant (tenant_id NULL = plataforma):
 * si está activo, cada mensaje del chat se retransmite al número del agente
 * por la cuenta de Hello elegida (wapi o Zernio), y lo que el agente
 * responda por WhatsApp vuelve a la conversación — ver Classes\WhatsappBridge.
 */
class ChannelSettings extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_livechat_settings';

    public $fillable = ['tenant_id', 'livechat_enabled', 'whatsapp_enabled', 'hello_account_id', 'whatsapp_to'];

    public $rules = [
        'hello_account_id' => 'nullable|integer',
        'whatsapp_to'      => 'nullable|string|max:32',
    ];

    protected $casts = ['livechat_enabled' => 'boolean', 'whatsapp_enabled' => 'boolean'];

    public $attributes = ['livechat_enabled' => true];

    public static function forScope(?int $tenantId): static
    {
        return static::firstOrCreate(['tenant_id' => $tenantId]);
    }

    /** Solo ajustes ya guardados, sin crear nada (camino caliente del widget). */
    public static function lookup(?int $tenantId): ?static
    {
        return static::where('tenant_id', $tenantId)->first();
    }

    /** Interruptor general del tenant. Sin registro guardado, el livechat está activo. */
    public static function livechatEnabled(?int $tenantId): bool
    {
        $settings = static::lookup($tenantId);

        return $settings ? (bool) $settings->livechat_enabled : true;
    }

    /** Cuentas de WhatsApp (wapi/Zernio) que este ámbito puede usar. */
    public static function accountsFor(?int $tenantId)
    {
        $query = Account::ofPlatform('whatsapp')->where('driver', '!=', 'livechat');

        return ($tenantId ? $query->forTenant($tenantId) : $query->whereNull('tenant_id')->whereNull('profile_id'))
            ->orderBy('label')->get();
    }

    public function getHelloAccountIdOptions(): array
    {
        return static::accountsFor($this->tenant_id)->mapWithKeys(fn ($a) => [
            $a->id => $a->label . ' (' . ($a->driver === 'wapi' ? 'WhatsApp Web' : 'WhatsApp Cloud API') . ($a->status === 'connected' ? '' : ' — ' . $a->status) . ')',
        ])->all();
    }

    public function beforeValidate()
    {
        if ($this->whatsapp_enabled && (!$this->hello_account_id || !$this->whatsapp_to)) {
            throw new \ValidationException(['whatsapp_enabled' => 'Para activarlo elige la cuenta de Hello y el número que recibirá los chats.']);
        }
    }

    public function beforeSave()
    {
        if ($this->whatsapp_to !== null && $this->whatsapp_to !== '') {
            $normalized = \Aero\Hello\Classes\PhoneNumber::normalize($this->whatsapp_to);
            if (!$normalized) {
                throw new \ValidationException(['whatsapp_to' => 'Número de WhatsApp no válido (formato internacional, 8 a 15 dígitos).']);
            }
            $this->whatsapp_to = $normalized;
        }

        if ($this->hello_account_id && !static::accountsFor($this->tenant_id)->contains('id', (int) $this->hello_account_id)) {
            throw new \ValidationException(['hello_account_id' => 'Cuenta de Hello no válida.']);
        }
    }

    /** Estado listo para el PWA / API. */
    public function toPayload(): array
    {
        return [
            'livechat_enabled' => (bool) $this->livechat_enabled,
            'enabled'    => (bool) $this->whatsapp_enabled,
            'account_id' => $this->hello_account_id ? (int) $this->hello_account_id : null,
            'to'         => $this->whatsapp_to,
        ];
    }
}
