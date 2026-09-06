<?php namespace Aero\Notify\Models;

use Aero\Notify\Classes\Support\Channels;
use Model;

/**
 * Plantilla de un evento para un canal y un idioma.
 *
 * tenant_id = 0 es la plantilla global de la plataforma; una fila con el
 * tenant_id de alguien la sobreescribe. Se usa 0 y no NULL porque en MySQL los
 * NULL no colisionan en un índice UNIQUE y se duplicarían las filas globales.
 */
class Template extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public const GLOBAL_TENANT = 0;

    public $table = 'aero_notify_templates';

    protected $guarded = [];

    protected $dates = ['checked_at'];

    protected $casts = [
        'tenant_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public $rules = [
        'code'    => 'required|max:120|unique:aero_notify_templates',
        'channel' => 'required',
        'locale'  => 'required|max:5',
        'body'    => 'required',
    ];

    public $attributes = [
        'tenant_id' => self::GLOBAL_TENANT,
        'locale'    => 'es',
        'format'    => 'twig',
        'is_active' => true,
    ];

    public $belongsTo = [
        'event'  => [Event::class],
        'tenant' => [\Aero\Sites\Models\Tenant::class],
    ];

    public function beforeValidate(): void
    {
        if (!$this->code && $this->event) {
            $this->code = $this->buildCode();
        }
    }

    /**
     * Código legible y estable: evento.canal.idioma, con el tenant delante
     * cuando es un override, para poder identificarlo de un vistazo en el log.
     */
    public function buildCode(): string
    {
        $parts = array_filter([
            $this->tenant_id ? "t{$this->tenant_id}" : null,
            $this->event?->code,
            $this->channel,
            $this->locale,
        ]);

        return implode('.', $parts);
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeGlobal($query)
    {
        return $query->where('tenant_id', self::GLOBAL_TENANT);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isGlobal(): bool
    {
        return (int) $this->tenant_id === self::GLOBAL_TENANT;
    }

    /**
     * Resolución en cascada: la del tenant en su idioma, la del tenant en
     * español, la global en el idioma, la global en español. Devuelve null si
     * no hay ninguna, y entonces la entrega se marca skipped: no_template.
     */
    public static function resolveFor(Event $event, string $channel, int $tenantId = 0, string $locale = 'es'): ?static
    {
        $candidates = [
            [$tenantId, $locale],
            [$tenantId, 'es'],
            [self::GLOBAL_TENANT, $locale],
            [self::GLOBAL_TENANT, 'es'],
        ];

        foreach ($candidates as [$tid, $loc]) {
            $template = static::active()
                ->where('event_id', $event->id)
                ->where('channel', $channel)
                ->where('tenant_id', $tid)
                ->where('locale', $loc)
                ->first();

            if ($template) {
                return $template;
            }
        }

        return null;
    }

    public function getChannelOptions(): array
    {
        return Channels::options();
    }

    public function hasSubject(): bool
    {
        return in_array($this->channel, Channels::withSubject(), true);
    }
}
