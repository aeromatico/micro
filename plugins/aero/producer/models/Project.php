<?php namespace Aero\Producer\Models;

use Model;

/**
 * Un video (o audio, más adelante) de principio a fin. `metadata` guarda lo que
 * las redes piden; `outputs` los archivos finales que ya están en Medios.
 */
class Project extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_producer_projects';

    public $fillable = [
        'tenant_id', 'kind', 'slug', 'name', 'topic', 'script', 'status', 'voice_id', 'background',
        'service_id', 'source_dir', 'metadata', 'outputs',
    ];

    public $jsonable = ['metadata', 'outputs'];

    public $rules = [
        'name' => 'required|max:190',
        'slug' => 'required|alpha_dash|max:80',
    ];

    public const METADATA_KEYS = ['title', 'description', 'tags', 'category_id', 'language', 'hashtags', 'social_text'];

    public function beforeValidate(): void
    {
        $this->rules['slug'] = 'required|alpha_dash|max:80|unique:aero_producer_projects,slug,'
            . ($this->id ?: 'NULL') . ',id,tenant_id,' . ($this->tenant_id ?: 'NULL');
    }

    public function getStatusOptions(): array
    {
        return ['draft' => 'Borrador', 'designed' => 'Diseñado', 'voiced' => 'Con voz', 'rendered' => 'Renderizado', 'published' => 'Publicado'];
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->getStatusOptions()[$this->status] ?? $this->status;
    }

    public function getCategoryOptions(): array
    {
        return class_exists(\Aero\Youtube\Models\Upload::class) ? \Aero\Youtube\Models\Upload::categories() : ['28' => 'Ciencia y tecnología'];
    }

    public function meta(string $key, $default = null)
    {
        return ((array) $this->metadata)[$key] ?? $default;
    }

    /** Problemas que impiden publicar tal cual; vacío = listo para aprobar. */
    public function metadataProblems(): array
    {
        $problems = [];
        $title = (string) $this->meta('title');
        if ($title === '') {
            $problems[] = 'Falta el título.';
        } elseif (mb_strlen($title) > 100) {
            $problems[] = 'El título supera 100 caracteres.';
        }
        if (trim((string) $this->meta('description')) === '') {
            $problems[] = 'Falta la descripción.';
        }
        if (mb_strlen((string) $this->meta('tags')) > 500) {
            $problems[] = 'Las etiquetas superan 500 caracteres.';
        }
        if (!$this->outputs) {
            $problems[] = 'Aún no hay videos terminados en Medios.';
        }

        return $problems;
    }

    public function getOutputsSummaryAttribute(): string
    {
        return collect((array) $this->outputs)
            ->map(fn ($o) => ($o['orientation'] ?? '?') . ' · ' . ($o['path'] ?? ''))
            ->implode("\n");
    }

    public function getReadyAttribute(): string
    {
        return $this->metadataProblems() ? 'Falta algo' : 'Listo';
    }
}
