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

    public $fillable = ['tenant_id', 'livechat_enabled', 'widget_mode', 'widget_whatsapp', 'custom_code', 'whatsapp_enabled', 'hello_account_id', 'whatsapp_to'];

    public const MODE_LIVECHAT = 'livechat', MODE_WHATSAPP = 'whatsapp', MODE_BOTH = 'both', MODE_CUSTOM = 'custom';

    public const MODES = [self::MODE_LIVECHAT, self::MODE_WHATSAPP, self::MODE_BOTH, self::MODE_CUSTOM];

    /** Tope del código de terceros (es texto que se sirve en cada carga del widget). */
    public const CUSTOM_CODE_MAX = 20000;

    public $rules = [
        'widget_mode'      => 'in:livechat,whatsapp,both,custom',
        'hello_account_id' => 'nullable|integer',
        'whatsapp_to'      => 'nullable|string|max:32',
    ];

    protected $casts = ['livechat_enabled' => 'boolean', 'whatsapp_enabled' => 'boolean'];

    public $attributes = ['livechat_enabled' => true, 'widget_mode' => 'livechat'];

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

    /** ¿Este tenant ofrece el chat de Hello a sus visitantes? Interruptor general + modo livechat o livechat+WhatsApp. */
    public static function chatAvailable(?int $tenantId): bool
    {
        $settings = static::lookup($tenantId);

        return !$settings || ($settings->livechat_enabled && in_array($settings->widget_mode, [self::MODE_LIVECHAT, self::MODE_BOTH], true));
    }

    /**
     * Lo que el widget necesita para pintarse (endpoint público `config`):
     * solo se incluye lo que el modo usa, nada más sale al navegador.
     */
    public static function widgetConfig(?int $tenantId): array
    {
        $settings = static::lookup($tenantId);
        $mode = $settings?->widget_mode ?: self::MODE_LIVECHAT;
        $enabled = $settings ? (bool) $settings->livechat_enabled : true;

        return [
            'enabled'     => $enabled,
            'mode'        => $mode,
            'whatsapp'    => in_array($mode, [self::MODE_WHATSAPP, self::MODE_BOTH], true) ? $settings?->widget_whatsapp : null,
            'custom_code' => $mode === self::MODE_CUSTOM ? $settings?->custom_code : null,
        ];
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
        if (in_array($this->widget_mode, [self::MODE_WHATSAPP, self::MODE_BOTH], true) && !\Aero\Hello\Classes\PhoneNumber::normalize($this->widget_whatsapp)) {
            throw new \ValidationException(['widget_whatsapp' => 'Ingresa el número de WhatsApp del icono flotante (formato internacional, 8 a 15 dígitos).']);
        }

        if ($this->widget_mode === self::MODE_CUSTOM && trim((string) $this->custom_code) === '') {
            throw new \ValidationException(['custom_code' => 'Pega el código que se incrustará en el sitio.']);
        }

        if (mb_strlen((string) $this->custom_code) > self::CUSTOM_CODE_MAX) {
            throw new \ValidationException(['custom_code' => 'El código es demasiado largo (máximo ' . self::CUSTOM_CODE_MAX . ' caracteres).']);
        }

        if ($this->whatsapp_enabled && (!$this->hello_account_id || !$this->whatsapp_to)) {
            throw new \ValidationException(['whatsapp_enabled' => 'Para activarlo elige la cuenta de Hello y el número que recibirá los chats.']);
        }
    }

    public function beforeSave()
    {
        if ($this->widget_whatsapp !== null && $this->widget_whatsapp !== '') {
            $this->widget_whatsapp = \Aero\Hello\Classes\PhoneNumber::normalize($this->widget_whatsapp);
        }
        else {
            $this->widget_whatsapp = null;
        }

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
            'widget_mode'      => $this->widget_mode ?: self::MODE_LIVECHAT,
            'widget_whatsapp'  => $this->widget_whatsapp,
            'custom_code'      => $this->custom_code,
            'enabled'    => (bool) $this->whatsapp_enabled,
            'account_id' => $this->hello_account_id ? (int) $this->hello_account_id : null,
            'to'         => $this->whatsapp_to,
        ];
    }
}
