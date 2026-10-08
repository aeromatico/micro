<?php namespace Aero\Workflows\Models;

use Model;

/**
 * Un flujo de automatización: un grafo de nodos (`graph`) con un disparador.
 * `expose_as_tool` lo ofrece, además, como herramienta al Super Chatbot IA
 * (solo si el bot tiene habilitada la categoría «workflows»).
 */
class Workflow extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_workflows_workflows';

    public $fillable = [
        'tenant_id', 'name', 'slug', 'description', 'is_active', 'status', 'trigger_type', 'trigger_config',
        'graph', 'expose_as_tool', 'tool_description', 'tool_schema',
    ];

    public $rules = [
        'name'         => 'required',
        'slug'         => 'required|alpha_dash',
        'trigger_type' => 'required|in:manual,event,message,webhook',
        'status'       => 'in:draft,published,archived',
    ];

    public $hasMany = [
        'runs' => [Run::class, 'key' => 'workflow_id'],
    ];

    public function beforeValidate(): void
    {
        $this->rules['slug'] = 'required|alpha_dash|unique:aero_workflows_workflows,slug,'
            . ($this->id ?: 'NULL') . ',id,tenant_id,' . ($this->tenant_id ?: 'NULL');
    }

    /** Los campos JSON se editan como texto: decodificarlos aquí, siempre como array. */
    public function jsonField(string $field): array
    {
        $raw = $this->attributes[$field] ?? null;

        if (is_array($raw)) {
            return $raw;
        }

        $decoded = is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : null;

        return is_array($decoded) ? $decoded : [];
    }

    public function beforeSave(): void
    {
        foreach (['trigger_config', 'graph', 'tool_schema'] as $field) {
            $raw = $this->attributes[$field] ?? null;

            if (is_array($raw)) {
                $this->attributes[$field] = json_encode($raw, JSON_UNESCAPED_UNICODE);
            }
            elseif (is_string($raw) && trim($raw) !== '') {
                json_decode($raw);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \ApplicationException("El campo {$field} no es un JSON válido: " . json_last_error_msg());
                }
            }
            else {
                $this->attributes[$field] = null;
            }
        }

        // Al PUBLICAR (pasar a «publicado») el diseño debe ser válido de punta a punta: estructura, salidas
        // conectadas y límites. Los ya publicados no se revalidan entero al editarlos (solo sus límites).
        $publishing = $this->exists && $this->status === 'published' && ($this->original['status'] ?? null) !== 'published';

        if ($publishing) {
            $errors = \Aero\Workflows\Classes\GraphValidator::validate($this->jsonField('graph'));

            if ($errors) {
                throw new \ApplicationException("No se puede publicar todavía:\n- " . implode("\n- ", array_slice($errors, 0, 8)));
            }
        }

        // Un borrador a medias puede guardarse; uno publicado no puede romper los límites de sus nodos.
        if ($this->status === 'published') {
            $errors = \Aero\Workflows\Classes\GraphValidator::nodeLimitErrors($this->jsonField('graph'));

            if ($errors) {
                throw new \ApplicationException("No se puede publicar el workflow:\n- " . implode("\n- ", array_slice($errors, 0, 8)));
            }
        }

        $this->version = (int) $this->version + ($this->exists && $this->isDirty('graph') ? 1 : 0);
    }

    public function afterSave(): void
    {
        \Aero\Workflows\Classes\Triggers::flush();
    }

    /** Sin claves foráneas en las tablas: se borran a mano las ejecuciones y sus pasos para no dejar huérfanos. */
    public function beforeDelete(): void
    {
        $runIds = Run::where('workflow_id', $this->id)->pluck('id');

        foreach ($runIds->chunk(500) as $chunk) {
            RunStep::whereIn('run_id', $chunk)->delete();
        }

        Run::where('workflow_id', $this->id)->delete();
    }

    public function afterDelete(): void
    {
        \Aero\Workflows\Classes\Triggers::flush();
    }

    public function getStatusOptions(): array
    {
        return ['draft' => 'Borrador', 'published' => 'Publicado', 'archived' => 'Archivado'];
    }

    public function getTriggerTypeOptions(): array
    {
        return [
            'manual'  => 'Manual / de prueba',
            'event'   => 'Evento de la plataforma (aero.*)',
            'message' => 'Mensaje entrante (Hello)',
            'webhook' => 'Webhook entrante',
        ];
    }

    /** Nombre de la herramienta que ve la IA: wf_<slug> con guiones bajos. */
    public function getToolNameAttribute(): string
    {
        return 'wf_' . str_replace('-', '_', $this->slug);
    }
}
