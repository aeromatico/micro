<?php namespace Aero\Notify\Models;

use Aero\Notify\Classes\Support\Audiences;
use Aero\Notify\Classes\Support\Channels;
use Model;

/**
 * Regla de entrega: para este evento, a esta audiencia, por este canal, con
 * esta plantilla y bajo estas condiciones.
 *
 * Herencia: se buscan las reglas del tenant; si el tenant no tiene NINGUNA para
 * el evento, se usan las globales (tenant_id = 0). La herencia es por evento
 * completo y no por canal a propósito: mezclar niveles produce combinaciones
 * imposibles de explicar en la interfaz.
 */
class Rule extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Sortable;

    public const GLOBAL_TENANT = 0;

    public $table = 'aero_notify_rules';

    protected $guarded = [];

    protected $jsonable = ['audience_filter', 'conditions'];

    protected $casts = [
        'tenant_id'         => 'integer',
        'delay_seconds'     => 'integer',
        'dedup_window_min'  => 'integer',
        'digest_window_min' => 'integer',
        'is_enabled'        => 'boolean',
    ];

    public $rules = [
        'event_id'          => 'required|exists:aero_notify_events,id',
        'audience'          => 'required|max:40',
        'channel'           => 'required|max:30',
        'delay_seconds'     => 'integer|min:0',
        'dedup_window_min'  => 'integer|min:0',
        'digest_window_min' => 'integer|min:0',
        'max_per_hour'      => 'nullable|integer|min:1',
        'priority'          => 'nullable|integer|between:1,9',
    ];

    public $attributes = [
        'tenant_id'         => self::GLOBAL_TENANT,
        'delay_seconds'     => 0,
        'dedup_window_min'  => 0,
        'digest_window_min' => 0,
        'is_enabled'        => true,
    ];

    public $belongsTo = [
        'event'    => [Event::class],
        'template' => [Template::class],
        'tenant'   => [\Aero\Sites\Models\Tenant::class],
    ];

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeGlobal($query)
    {
        return $query->where('tenant_id', self::GLOBAL_TENANT);
    }

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    public function isGlobal(): bool
    {
        return (int) $this->tenant_id === self::GLOBAL_TENANT;
    }

    /**
     * Reglas efectivas de un evento para un tenant, aplicando la herencia.
     *
     * @return \October\Rain\Database\Collection
     */
    public static function effectiveFor(Event $event, int $tenantId = 0)
    {
        if ($tenantId !== self::GLOBAL_TENANT) {
            $own = static::where('event_id', $event->id)
                ->forTenant($tenantId)
                ->orderBy('sort_order')
                ->get();

            // Basta con que el tenant tenga una fila para ese evento para que
            // deje de heredar: lo que ve en la interfaz es lo que se envía.
            if ($own->isNotEmpty()) {
                return $own->where('is_enabled', true)->values();
            }
        }

        return static::where('event_id', $event->id)
            ->global()
            ->enabled()
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Materializa las reglas globales de un evento como reglas propias del
     * tenant, para que pueda ajustarlas sin tocar las de la plataforma.
     *
     * @return static[]
     */
    public static function materializeFor(Event $event, int $tenantId): array
    {
        if ($tenantId === self::GLOBAL_TENANT) {
            return [];
        }

        $created = [];

        foreach (static::where('event_id', $event->id)->global()->get() as $globalRule) {
            $exists = static::where('event_id', $event->id)
                ->forTenant($tenantId)
                ->where('audience', $globalRule->audience)
                ->where('channel', $globalRule->channel)
                ->exists();

            if ($exists) {
                continue;
            }

            $copy = $globalRule->replicate();
            $copy->tenant_id = $tenantId;
            $copy->save();

            $created[] = $copy;
        }

        return $created;
    }

    public function getAudienceOptions(): array
    {
        return Audiences::options();
    }

    public function getChannelOptions(): array
    {
        return Channels::options();
    }

    public function getTemplateIdOptions(): array
    {
        if (!$this->event_id) {
            return [];
        }

        return static::templateQuery($this->event_id, $this->channel, (int) $this->tenant_id)
            ->pluck('code', 'id')
            ->all();
    }

    protected static function templateQuery(int $eventId, ?string $channel, int $tenantId)
    {
        $query = Template::where('event_id', $eventId)
            ->whereIn('tenant_id', array_unique([$tenantId, self::GLOBAL_TENANT]));

        if ($channel) {
            $query->where('channel', $channel);
        }

        return $query;
    }
}
