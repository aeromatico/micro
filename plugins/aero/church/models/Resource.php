<?php namespace Aero\Church\Models;

use Model;

/** Material de estudio descargable (estudio bíblico, guía de grupo, recurso de ministerio). */
class Resource extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \Aero\Church\Classes\TenantOwned;

    public const KINDS = ['study' => 'Estudio bíblico', 'group_guide' => 'Guía para grupos', 'ministry' => 'Recurso de ministerio'];

    public $table = 'aero_church_resources';

    public static $audited = true;
    public static $auditLabel = 'recurso';
    public static $auditType = 'resource';

    public $attributes = ['kind' => 'study', 'visibility' => 'members', 'is_published' => false];

    public $fillable = ['tenant_id', 'sermon_id', 'title', 'description', 'kind', 'visibility', 'is_published'];

    public $nullable = ['sermon_id'];

    public $rules = [
        'title'      => 'required|string|max:255',
        'kind'       => 'required|in:study,group_guide,ministry',
        'visibility' => 'required|in:public,members',
    ];

    public $belongsTo = [
        'sermon' => [Sermon::class, 'key' => 'sermon_id'],
    ];

    /** Privado: nunca se sirve por URL directa, solo por la ruta que verifica visibilidad. */
    public $attachOne = [
        'file' => [\System\Models\File::class, 'public' => false],
    ];

    public function beforeValidate(): void
    {
        $this->assertReferencesVisible(['sermon_id' => Sermon::class]);
    }

    public function scopeShown($query, bool $isMember = false)
    {
        $query->where('is_published', true);

        return $isMember ? $query : $query->where('visibility', 'public');
    }

    public function getKindOptions(): array
    {
        return self::KINDS;
    }

    public function getVisibilityOptions(): array
    {
        return ['public' => 'Público', 'members' => 'Solo miembros'];
    }

    public function getSermonIdOptions(): array
    {
        return Sermon::visible()->orderByDesc('preached_on')->pluck('title', 'id')->all();
    }
}
