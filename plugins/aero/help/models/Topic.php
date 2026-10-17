<?php namespace Aero\Help\Models;

use Model;

/**
 * Un tema de ayuda: reúne, para una pantalla o una duda frecuente, el artículo,
 * la guía interactiva y/o el flujo guiado. `screens` son patrones
 * `plugin/controlador/acción` (con comodines) de las pantallas donde se sugiere.
 */
class Topic extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_help_topics';

    public $fillable = [
        'slug', 'title', 'summary', 'keywords', 'plugin', 'screens', 'article_id', 'guide_id', 'flow_id',
        'sort_order', 'is_published',
    ];

    public $rules = [
        'title' => 'required|max:255',
        'slug'  => 'required|alpha_dash|max:120|unique:aero_help_topics,slug',
    ];

    public $jsonable = ['screens'];

    protected $casts = ['is_published' => 'boolean'];

    public $belongsTo = [
        'flow' => [Flow::class, 'key' => 'flow_id'],
    ];

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function getFlowIdOptions(): array
    {
        return ['' => '— Sin flujo —'] + Flow::orderBy('title')->pluck('title', 'id')->all();
    }

    public function getArticleIdOptions(): array
    {
        if (!\Aero\Help\Classes\Integrations::docs()) {
            return [];
        }

        return ['' => '— Sin artículo —'] + \Aero\Docs\Models\Article::platform()->orderBy('title')->pluck('title', 'id')->all();
    }

    public function getGuideIdOptions(): array
    {
        if (!\Aero\Help\Classes\Integrations::docs()) {
            return [];
        }

        return ['' => '— Sin guía —'] + \Aero\Docs\Models\Guide::platform()->orderBy('title')->get()
            ->mapWithKeys(fn ($g) => [$g->id => $g->select_label])->all();
    }
}
