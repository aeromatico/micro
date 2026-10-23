<?php namespace Aero\Church\Models;

use Model;

/** Comunicado por WhatsApp a un segmento de la congregación, con su historial de envío. */
class Announcement extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \Aero\Church\Classes\TenantOwned;

    public const KINDS = ['announcement' => 'Comunicado', 'reminder' => 'Recordatorio', 'schedule_change' => 'Cambio o cancelación', 'birthday' => 'Cumpleaños'];
    public const STATUSES = ['draft' => 'Borrador', 'sending' => 'Enviando', 'sent' => 'Enviado'];

    public $table = 'aero_church_announcements';

    public $attributes = ['kind' => 'announcement', 'status' => 'draft'];

    public $fillable = ['tenant_id', 'title', 'body', 'kind', 'audience'];

    public $jsonable = ['audience'];

    public $rules = [
        'title' => 'required|string|max:255',
        'body'  => 'required|string|max:1500',
        'kind'  => 'required|in:announcement,reminder,schedule_change,birthday',
    ];

    public $dates = ['sent_at'];

    public $hasMany = [
        'recipients' => [AnnouncementRecipient::class, 'key' => 'announcement_id'],
    ];

    public function beforeCreate(): void
    {
        $this->created_by = \BackendAuth::getUser()?->id;
    }

    public function beforeUpdate(): void
    {
        if ($this->getOriginal('status') !== 'draft' && array_intersect(['title', 'body', 'audience', 'kind'], array_keys($this->getDirty()))) {
            throw new \ApplicationException('Un comunicado ya enviado no se edita: crea uno nuevo.');
        }
    }

    public function beforeDelete(): void
    {
        if ($this->status === 'sending') {
            throw new \ApplicationException('El comunicado se está enviando.');
        }
    }

    public function getKindOptions(): array
    {
        return self::KINDS;
    }

    /** Opciones para el segmento (campos del formulario en `audience`). */
    public function getAudienceStatusesOptions(): array
    {
        return Member::STATUSES;
    }

    public function getAudienceBranchIdOptions(): array
    {
        return Branch::visible()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getAudienceGroupIdOptions(): array
    {
        return Group::visible()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getAudienceEventIdOptions(): array
    {
        return ChurchEvent::visible()->where('starts_at', '>=', now()->subDays(7))->orderBy('starts_at')->get()
            ->mapWithKeys(fn ($e) => [$e->id => $e->title . ' (' . $e->starts_at->format('d/m H:i') . ')'])->all();
    }
}
