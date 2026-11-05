<?php namespace Aero\Tickets\Models;

use Model;

/** Bitácora de auditoría de la plataforma. Solo se agrega: no se edita ni se borra. */
class Audit extends Model
{
    public $table = 'aero_tickets_audit';
    public $timestamps = false;
    public $guarded = [];
    public $jsonable = ['changes'];
    public $dates = ['created_at'];

    public static array $subjects = [
        'event' => 'Evento', 'organizer' => 'Organizador', 'user' => 'Usuario', 'buyer' => 'Comprador', 'commission_rule' => 'Regla de comisión',
        'setting' => 'Parámetros', 'location' => 'Ubicación', 'template' => 'Plantilla', 'order' => 'Orden',
    ];

    public function getSubjectTypeOptions(): array { return self::$subjects; }
    public function getSubjectLabelAttribute(): string { return self::$subjects[$this->subject_type] ?? $this->subject_type; }

    /** Resumen legible de {campo: [antes, después]} para la lista. */
    public function getChangesSummaryAttribute(): string
    {
        return collect((array) $this->changes)->map(fn ($v, $k) => $k . ': ' . mb_substr((string) ($v[0] ?? ''), 0, 40) . ' → ' . mb_substr((string) ($v[1] ?? ''), 0, 40))->implode(' · ');
    }

    public function beforeCreate(): void { $this->created_at = $this->created_at ?: now(); }
    public function beforeUpdate(): void { throw new \ApplicationException('La auditoría no se modifica.'); }
    public function beforeDelete(): void { throw new \ApplicationException('La auditoría no se borra.'); }

    public function scopeVisible($q) { return \Aero\Tickets\Classes\Admin::can('view') ? $q : $q->whereRaw('1 = 0'); }
}
