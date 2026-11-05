<?php namespace Aero\Tickets\Classes;

use Aero\Tickets\Models\Event;

/**
 * Para los modelos hijos de un evento: heredan el tenant del evento y, desde el
 * panel, solo pueden colgar de eventos que el usuario ve (id manipulado en el POST).
 */
trait BelongsToEvent
{
    public static function bootBelongsToEvent(): void
    {
        static::saving(function ($model) {
            $event = $model->event_id ? Event::withTrashed()->find($model->event_id) : null;
            if (!$event) {
                throw new \ApplicationException('Elige un evento válido.');
            }
            if (\BackendAuth::getUser() && !Event::visible()->whereKey($event->id)->exists()) {
                throw new \ApplicationException('El evento no es válido.');
            }
            $model->tenant_id = $event->tenant_id;
        });
    }

    public function scopeVisible($q)
    {
        return CurrentTenant::isAdmin() ? $q : $this->scopeForTenant($q, CurrentTenant::id());
    }

    public function scopeForTenant($q, ?int $tenantId)
    {
        return $tenantId ? $q->where($this->getTable() . '.tenant_id', $tenantId) : $q->whereRaw('1 = 0');
    }

    public function getEventIdOptions(): array
    {
        return Event::visible()->orderByDesc('starts_at')->pluck('name', 'id')->all();
    }

    /** ¿El registro $id de $class existe, es de este evento y (desde el panel) lo ve el usuario? */
    protected function refBelongsToEvent(string $class, $id): bool
    {
        $q = $class::where('event_id', $this->event_id)->whereKey($id);
        if (\BackendAuth::getUser()) {
            $q->visible();
        }

        return $q->exists();
    }
}
