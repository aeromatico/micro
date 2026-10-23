<?php namespace Aero\Church\Models;

use Aero\Church\Classes\Embed;
use Aero\Church\Classes\Slugs;
use Model;

/** Predicación o enseñanza (video/audio) con su resumen y recursos. Pública o solo para miembros. */
class Sermon extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \Aero\Church\Classes\TenantOwned;

    public $table = 'aero_church_sermons';

    public $attributes = ['visibility' => 'public', 'is_published' => false];

    public $fillable = [
        'tenant_id', 'series_id', 'title', 'preacher', 'topic', 'preached_on', 'summary', 'media_url', 'audio_url', 'visibility', 'is_published',
    ];

    public $nullable = ['series_id', 'preached_on'];

    public $rules = [
        'title'      => 'required|string|max:255',
        'visibility' => 'required|in:public,members',
    ];

    public $dates = ['preached_on'];

    public $belongsTo = [
        'series' => [Series::class, 'key' => 'series_id'],
    ];

    public $hasMany = [
        'resources' => [Resource::class, 'key' => 'sermon_id'],
    ];

    public function beforeValidate(): void
    {
        $this->assertReferencesVisible(['series_id' => Series::class]);

        foreach (['media_url', 'audio_url'] as $f) {
            if ($this->{$f} && !Embed::isHttps($this->{$f})) {
                throw new \ApplicationException('Los enlaces deben empezar con https://');
            }
        }
    }

    public function beforeSave(): void
    {
        if (!$this->slug) {
            $this->slug = Slugs::unique(self::class, $this->tenant_id, $this->title, $this->id);
        }
    }

    /** Lo que ve un visitante (sin sesión) o un miembro. */
    public function scopeShown($query, bool $isMember = false)
    {
        $query->where('is_published', true);

        return $isMember ? $query : $query->where('visibility', 'public');
    }

    public function getEmbedSrcAttribute(): ?string
    {
        return Embed::src($this->media_url);
    }

    public function getVisibilityOptions(): array
    {
        return ['public' => 'Público', 'members' => 'Solo miembros'];
    }

    public function getSeriesIdOptions(): array
    {
        return Series::visible()->orderBy('name')->pluck('name', 'id')->all();
    }
}
