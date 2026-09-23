<?php namespace Aero\Services\Models;

use Aero\Services\Classes\PluginCatalog;
use Model;

class Service extends Model
{
    use \October\Rain\Database\Traits\Sluggable;
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_services_services';

    public $fillable = [
        'category_id', 'name', 'slug', 'summary', 'description', 'code', 'type', 'pricing_mode', 'credit_price', 'credit_type', 'has_pro', 'pro_features', 'price', 'price_from', 'setup_fee',
        'currency', 'billing_period', 'delivery_days', 'features', 'requirements', 'plugin_links',
        'sort_order', 'is_active', 'is_featured', 'in_megamenu',
    ];

    public $slugs = ['slug' => 'name'];

    public $rules = [
        'name'          => 'required|max:255',
        'slug'          => 'required|alpha_dash',
        'type'          => 'required|in:one_time,recurring,project,hourly',
        'price'         => 'nullable|numeric|min:0',
        'setup_fee'     => 'nullable|numeric|min:0',
        'currency'      => 'required|in:BOB,USD',
        'pricing_mode'  => 'required|in:money,credits,both',
        'credit_price'  => 'nullable|integer|min:1|required_if:pricing_mode,credits,both',
        'credit_type'   => 'nullable|required_if:pricing_mode,credits,both',
        'delivery_days' => 'nullable|integer|min:0',
    ];

    public $nullable = ['credit_price', 'credit_type', 'price', 'setup_fee', 'delivery_days', 'billing_period', 'category_id'];

    public $attributes = ['type' => 'one_time', 'currency' => 'BOB', 'pricing_mode' => 'money', 'has_pro' => false, 'is_active' => true, 'sort_order' => 0];

    public $jsonable = ['features', 'requirements', 'plugin_links', 'pro_features'];

    protected $casts = [
        'price_from'  => 'boolean',
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

    public function getTypeOptions(): array
    {
        return [
            'one_time'  => 'Pago único',
            'recurring' => 'Suscripción / recurrente',
            'project'   => 'Proyecto',
            'hourly'    => 'Por hora',
        ];
    }

    public function getPricingModeOptions(): array
    {
        return ['money' => 'Solo dinero', 'credits' => 'Solo créditos', 'both' => 'Dinero o créditos (ambos)'];
    }

    /** [código => etiqueta] de los tipos de crédito activos; vacío si Aero.Credits no está instalado. */
    public function getCreditTypeOptions(): array
    {
        if (!class_exists(\Aero\Credits\Models\CreditType::class)) {
            return [];
        }

        return \Aero\Credits\Models\CreditType::active()->pluck('label', 'code')->all();
    }

    public function getBillingPeriodOptions(): array
    {
        return ['monthly' => 'Mensual', 'quarterly' => 'Trimestral', 'yearly' => 'Anual'];
    }

    public function getCategoryIdOptions(): array
    {
        return Category::orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all();
    }

    public function beforeSave(): void
    {
        // El periodo de cobro solo aplica a servicios recurrentes.
        if ($this->type !== 'recurring') {
            $this->billing_period = null;
        }

        // Cada modalidad conserva solo los precios que usa.
        if ($this->pricing_mode === 'credits') {
            $this->price = $this->setup_fee = null;
        }
        elseif ($this->pricing_mode === 'money') {
            $this->credit_price = $this->credit_type = null;
        }

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
