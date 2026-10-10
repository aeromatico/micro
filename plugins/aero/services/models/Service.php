<?php namespace Aero\Services\Models;

use Aero\Services\Classes\PluginCatalog;
use Model;

class Service extends Model
{
    use \October\Rain\Database\Traits\Sluggable;
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_services_services';

    public $fillable = [
        'name', 'slug', 'icon', 'summary', 'description', 'code', 'has_pro', 'pro_features', 'plans',
        'features', 'requirements', 'plugin_links',
        'sort_order', 'is_active', 'is_featured', 'in_megamenu',
    ];

    public $slugs = ['slug' => 'name'];

    public $rules = [
        'name' => 'required|max:255',
        'slug' => 'required|alpha_dash',
    ];

    public $attributes = ['has_pro' => false, 'is_active' => true, 'sort_order' => 0];

    public $jsonable = ['features', 'requirements', 'plugin_links', 'pro_features', 'plans'];

    protected $casts = [
        'has_pro'     => 'boolean',
        'is_active'   => 'boolean',
        'is_featured' => 'boolean',
        'in_megamenu' => 'boolean',
    ];

    public $belongsToMany = [
        'categories' => [
            Category::class,
            'table'    => 'aero_services_category_service',
            'key'      => 'service_id',
            'otherKey' => 'category_id',
            'order'    => 'sort_order',
        ],
        'articles' => [
            \Aero\Docs\Models\Article::class,
            'table'    => 'aero_services_service_article',
            'key'      => 'service_id',
            'otherKey' => 'article_id',
            'scope'    => 'platform',
            'order'    => 'title',
        ],
        'guides' => [
            \Aero\Docs\Models\Guide::class,
            'table'    => 'aero_services_service_guide',
            'key'      => 'service_id',
            'otherKey' => 'guide_id',
            'scope'    => 'platform',
            'order'    => 'aero_docs_guides.sort_order',
        ],
    ];

    public $attachOne = [
        'cover' => \System\Models\File::class,
    ];

    /** Categoría principal (la primera por orden), para migas de pan y etiquetas de una sola categoría. */
    public function getCategoryAttribute(): ?Category
    {
        return $this->categories->first();
    }

    /**
     * Plugins con los que se conecta el servicio, listos para pintar: nombre (el del servicio que
     * los vende si existe, si no el del plugin), enlace y nota. Excluye el plugin base («construido con»).
     */
    public function getIntegrationsAttribute(): array
    {
        $links = collect((array) $this->plugin_links)->filter(fn ($l) => !empty($l['plugin']))->values();
        $base = $links->firstWhere('relation', 'built_with')['plugin'] ?? null;
        $links = $links->reject(fn ($l) => $l['plugin'] === $base);

        if ($links->isEmpty()) {
            return [];
        }

        $services = static::active()->where('id', '!=', $this->id)->whereNotNull('plugin_links')->get();
        $plugins = \System\Classes\PluginManager::instance();

        return $links->map(function (array $l) use ($services, $plugins) {
            $owner = $services->first(fn ($s) => collect((array) $s->plugin_links)
                ->contains(fn ($x) => ($x['plugin'] ?? null) === $l['plugin'] && ($x['relation'] ?? null) === 'built_with'));
            $plugin = $plugins->findByIdentifier($l['plugin']);

            return [
                'plugin'   => $l['plugin'],
                'name'     => $owner?->name ?: trans((string) ($plugin?->pluginDetails()['name'] ?? $l['plugin'])),
                'url'      => $owner ? url('plugin/' . $owner->slug) : null,
                'relation' => $l['relation'] ?? 'integrates',
                'note'     => trim((string) ($l['note'] ?? '')),
            ];
        })->all();
    }

    /** ¿Pertenece a la categoría «Rubros»? Usa su propio diseño de página. */
    public function getIsRubroAttribute(): bool
    {
        return $this->categories->contains('slug', 'rubros');
    }

    /** Guías interactivas vinculadas Y publicadas: lo que se renderiza dentro de /plugin/{slug}. */
    /**
     * `code` separado en [resto, sección "Documentación", sección "Preguntas frecuentes"]
     * para que la página intercale las guías/documentación (en pestañas) antes de las FAQ.
     * Las secciones que no existen van vacías.
     */
    public function getCodePartsAttribute(): array
    {
        $code = (string) $this->code;
        $found = ['Documentación' => '', 'Preguntas frecuentes' => ''];

        if (preg_match_all('#<section\b.*?</section>#is', $code, $m)) {
            foreach (array_reverse($m[0]) as $section) {
                foreach ($found as $title => $html) {
                    if ($html === '' && preg_match('#<h2\b[^>]*>\s*' . preg_quote($title, '#') . '#iu', $section)) {
                        $pos = strrpos($code, $section);
                        $code = substr($code, 0, $pos) . substr($code, $pos + strlen($section));
                        $found[$title] = $section;
                        break;
                    }
                }
            }
        }

        return [$code, $found['Documentación'], $found['Preguntas frecuentes']];
    }

    public function getPublishedGuidesAttribute()
    {
        return $this->guides()->published()->orderBy('aero_docs_guides.sort_order')->orderBy('aero_docs_guides.title')->get();
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

        if (($plan['type'] ?? null) === 'free') {
            return 'Gratis';
        }

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

    /**
     * Líneas de precio de un plan para el sitio público: dinero, créditos (con
     * su equivalencia en Bs debajo) o "a cotizar". Cada línea trae ['primary' => bool, 'text' => string].
     */
    public function planPriceLines(array $plan): array
    {
        if (($plan['type'] ?? null) === 'free') {
            return [['primary' => true, 'text' => 'Gratis']];
        }

        $lines = [];
        $mode = $plan['pricing_mode'] ?? 'money';

        if (in_array($mode, ['money', 'both'], true)) {
            if (filled($plan['price'] ?? null)) {
                $prefix = !empty($plan['price_from']) ? 'Desde ' : '';
                $lines[] = ['primary' => true, 'text' => $prefix . ($plan['currency'] ?? 'BOB') . ' ' . $plan['price']];
            }
            elseif ($mode === 'money') {
                $lines[] = ['primary' => true, 'text' => 'A cotizar'];
            }
        }

        if (in_array($mode, ['credits', 'both'], true) && filled($plan['credit_price'] ?? null)) {
            $type = class_exists(\Aero\Credits\Models\CreditType::class)
                ? \Aero\Credits\Models\CreditType::findByCode((string) ($plan['credit_type'] ?? ''))
                : null;

            $lines[] = ['primary' => empty($lines), 'text' => $plan['credit_price'] . ' ' . ($type->label ?? 'créditos')];

            if ($type && $type->price_bob) {
                $bob = rtrim(rtrim(number_format($plan['credit_price'] * $type->price_bob, 2, '.', ''), '0'), '.');
                $lines[] = ['primary' => false, 'text' => '≈ Bs ' . $bob];
            }
        }

        return $lines ?: [['primary' => true, 'text' => 'A cotizar']];
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

                if ($plan['type'] === 'free') {
                    $plan['price'] = $plan['setup_fee'] = $plan['credit_price'] = $plan['credit_type'] = null;
                    $plan['price_from'] = false;
                }
                elseif ($plan['pricing_mode'] === 'credits') {
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
        return $query->whereHas('categories.collections', fn ($q) => is_numeric($collection)
            ? $q->where('aero_services_collections.id', $collection)
            : $q->where('aero_services_collections.slug', $collection));
    }
}
