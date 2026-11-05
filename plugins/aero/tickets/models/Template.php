<?php namespace Aero\Tickets\Models;

use Aero\Tickets\Classes\Audit as AuditLog;
use Aero\Tickets\Classes\TemplateDefaults;
use Model;

/** Plantilla de aviso (correo). El código es fijo; el administrador edita asunto y cuerpo. */
class Template extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_tickets_templates';
    public $fillable = ['subject', 'body', 'is_active'];
    public $rules = ['subject' => 'required|string|max:255', 'body' => 'required|string|max:20000'];

    public function beforeSave(): void
    {
        if (in_array($this->code, TemplateDefaults::CRITICAL, true)) {
            $this->is_active = true;           // sin estas el comprador no recibe lo que pagó
        }
    }

    public function afterSave(): void
    {
        AuditLog::record('template.updated', 'template', $this->id, ['note' => $this->code, 'changes' => AuditLog::diff($this->getOriginal(), $this->getAttributes(), ['subject', 'is_active'])]);
    }

    public function beforeDelete(): void { throw new \ApplicationException('Las plantillas no se eliminan: edítalas o desactívalas.'); }

    public function getDefaultAttribute(): array { return TemplateDefaults::all()[$this->code] ?? []; }
}
