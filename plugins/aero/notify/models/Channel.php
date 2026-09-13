<?php namespace Aero\Notify\Models;

use Aero\Notify\Classes\Support\Channels;
use Crypt;
use Model;

/**
 * Canal propio de un tenant: sus credenciales para email (SMTP propio),
 * whatsapp (cuenta Hello explícita), telegram (bot) o sms (Twilio), más la
 * dirección de destino. Notify::deliverOne() la consulta para cualquier
 * evento con Rule en ese canal — no es específica de ningún evento.
 *
 * Sin fila para (tenant, canal): la entrega sigue con la dirección resuelta
 * por AudienceResolver y las credenciales de plataforma, igual que si esta
 * tabla no existiera. Es 100% opt-in.
 */
class Channel extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Sortable;

    public $table = 'aero_notify_channels';

    public $fillable = [
        'tenant_id', 'channel', 'label', 'config', 'is_enabled', 'sort_order',
    ];

    public $rules = [
        'tenant_id' => 'required|exists:aero_sites_tenants,id',
        'channel'   => 'required',
        'label'     => 'required',
    ];

    public $belongsTo = [
        'tenant' => [\Aero\Sites\Models\Tenant::class],
    ];

    public function getConfigAttribute($value): array
    {
        if (!$value) {
            return [];
        }

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

    public function getTenantIdOptions(): array
    {
        return \Aero\Sites\Models\Tenant::orderBy('name')->pluck('name', 'id')->all();
    }

    /** Solo los canales que tienen sentido con credenciales propias por tenant. */
    public function getChannelOptions(): array
    {
        return array_intersect_key(Channels::options(), array_flip([
            Channels::EMAIL, Channels::WHATSAPP, Channels::TELEGRAM, Channels::SMS,
        ]));
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

    /** Primer canal habilitado del tenant para ese código de canal, o null. */
    public static function activeFor(int $tenantId, string $channel): ?self
    {
        if (!$tenantId) {
            return null;
        }

        return static::forTenant($tenantId)
            ->where('channel', $channel)
            ->enabled()
            ->orderBy('sort_order')
            ->first();
    }

    /**
     * Dirección de destino declarada en config, según la clave que usa cada
     * canal (mismo vocabulario que el sistema legacy que esto reemplaza).
     */
    public function destinationAddress(): ?string
    {
        $config = $this->config;

        return match ($this->channel) {
            Channels::EMAIL    => $config['to'] ?? null,
            Channels::WHATSAPP => $config['whatsapp_to'] ?? null,
            Channels::TELEGRAM => $config['chat_id'] ?? null,
            Channels::SMS      => $config['sms_to'] ?? null,
            default            => null,
        };
    }
}
