<?php namespace Aero\Services\Models;

use Aero\Services\Classes\PluginCatalog;
use Model;

class Service extends Model
{
    use \October\Rain\Database\Traits\Sluggable;
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_services_services';

    public $fillable = [
        'category_id', 'name', 'slug', 'summary', 'description', 'code', 'has_pro', 'pro_features', 'plans',
        'features', 'requirements', 'plugin_links',
        'sort_order', 'is_active', 'is_featured', 'in_megamenu',
    ];

    public $slugs = ['slug' => 'name'];

    public $rules = [
        'name' => 'required|max:255',
        'slug' => 'required|alpha_dash',
    ];

    public $nullable = ['category_id'];

    public $attributes = ['has_pro' => false, 'is_active' => true, 'sort_order' => 0];

    public $jsonable = ['features', 'requirements', 'plugin_links', 'pro_features', 'plans'];

    protected $casts = [
        'has_pro'     => 'boolean',
        'is_active'   => 'boolean',
        'is_featured' => 'boolean',
        'in_megamenu' => 'boolean',
    ];

    public $belongsTo = [
        'category' => [Category::class, 'key' => 'category_id'],
    ];

    public $belongsToMany = [
        'articles' => [
            \Aero\Docs\Models\Article::class,
            'table'    => 'aero_services_service_article',
            'key'      => 'service_id',
            'otherKey' => 'article_id',
            'scope'    => 'platform',
            'order'    => 'title',
        ],
        'collections' => [
            Collection::class,
            'table'    => 'aero_services_collection_service',
            'key'      => 'service_id',
            'otherKey' => 'collection_id',
            'order'    => 'name',
        ],
    ];

    public $attachOne = [
        'cover' => \System\Models\File::class,
    ];

    use \October\Rain\Database\Traits\Nullable;

    public function getCategoryIdOptions(): array
    {
        return Category::orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all();
    }

    /** Etiqueta corta del/los planes, para listados: "Bs 100" · "3 planes" · "A cotizar". */
    public function getPlansSummaryAttribute(): string
    {
        $plans = collect((array) $this->plans);

        if ($plans->isEmpty()) {
            return '—';
        }

        if ($plans->count() > 1) {
            return $plans->count() . ' planes';
        }

        $plan = $plans->first();
        $mode = $plan['pricing_mode'] ?? 'money';

        if (in_array($mode, ['money', 'both'], true) && filled($plan['price'] ?? null)) {
            $prefix = !empty($plan['price_from']) ? 'Desde ' : '';

            return $prefix . ($plan['currency'] ?? 'BOB') . ' ' . $plan['price'];
        }

        if (in_array($mode, ['credits', 'both'], true) && filled($plan['credit_price'] ?? null)) {
            return $plan['credit_price'] . ' créditos';
        }

        return 'A cotizar';
    }

    public function beforeSave(): void
    {
        // Cada plan conserva solo los campos que corresponden a su modalidad de cobro y tipo.
        $plans = collect((array) $this->plans)
            ->filter(fn ($p) => filled($p['name'] ?? null))
            ->map(function (array $plan) {
                $plan += ['type' => 'one_time', 'pricing_mode' => 'money', 'currency' => 'BOB'];

                if ($plan['type'] !== 'recurring') {
                    $plan['billing_period'] = null;
                }

                if ($plan['pricing_mode'] === 'credits') {
                    $plan['price'] = $plan['setup_fee'] = null;
                }
                elseif ($plan['pricing_mode'] === 'money') {
                    $plan['credit_price'] = $plan['credit_type'] = null;
                }

                return $plan;
            })
            ->values()
            ->all();

        $this->plans = $plans ?: null;

        $this->pro_features = $this->has_pro
            ? (collect((array) $this->pro_features)->filter(fn ($f) => trim((string) ($f['text'] ?? '')) !== '')->values()->all() ?: null)
            : null;

        // Plugins repetidos en los vínculos: queda el último.
        $links = collect((array) $this->plugin_links)
            ->filter(fn ($l) => !empty($l['plugin']))
            ->keyBy('plugin')
            ->values()
            ->all();

        $this->plugin_links = $links ?: null;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePro($query)
    {
        return $query->where('has_pro', true);
    }

    /** Públicos y marcados para el megamenú. */
    public function scopeInMegamenu($query)
    {
        return $query->where('is_active', true)->where('in_megamenu', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /** Servicios vinculados a un plugin (p. ej. 'Aero.Shop'), sea cual sea la relación. */
    public function scopeForPlugin($query, string $pluginCode, ?string $relation = null)
    {
        $needle = ['plugin' => $pluginCode] + ($relation ? ['relation' => $relation] : []);

        return $query->whereJsonContains('plugin_links', $needle);
    }

    /** Servicios sin vínculo a ningún plugin (genéricos). */
    public function scopeGeneric($query)
    {
        return $query->whereNull('plugin_links');
    }

    /** Servicios de una colección, por slug o id. */
    public function scopeInCollection($query, string|int $collection)
    {
        return $query->whereHas('collections', fn ($q) => is_numeric($collection)
            ? $q->where('aero_services_collections.id', $collection)
            : $q->where('aero_services_collections.slug', $collection));
    }
}
