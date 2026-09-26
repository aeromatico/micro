<?php namespace Aero\Hub\Models;

use Model;

/**
 * Un endpoint del catálogo de YepAPI, reflejado 1:1 (mismo path/método) para
 * proxyearlo bajo /hub. `Aero\Hub\Classes\CatalogSync` es la única fuente que
 * escribe las columnas de metadata (parseadas del spec); el superadmin solo
 * edita el bloque de precio/activación desde el backend — ver
 * `afterSave()`, que mantiene una fila espejo en `Aero.Credits\CreditAction`
 * (código `hub.<code>`) para que el ledger y el explorador de Aero.Api
 * funcionen igual que para el resto del catálogo de acciones facturables.
 */
class HubEndpoint extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_hub_endpoints';

    public $fillable = [
        'code', 'category', 'division', 'path', 'method', 'summary', 'description',
        'request_schema', 'pricing_type', 'unit_size', 'unit_cost_usd', 'count_path',
        'reference_cost_usd', 'margin_type', 'margin_value', 'credit_type_id', 'credit_cost',
        'overage_credit_cost', 'is_streaming', 'is_async', 'is_active', 'last_synced_at',
    ];

    public $rules = [
        'code'   => 'required|unique:aero_hub_endpoints,code',
        'path'   => 'required',
        'method' => 'required',
    ];

    public $jsonable = ['request_schema'];

    protected $dates = ['last_synced_at'];

    public $attributes = [
        'pricing_type'  => 'fixed',
        'credit_cost'   => 0,
        'is_streaming'  => false,
        'is_async'      => false,
        'is_active'     => false,
    ];

    protected $casts = [
        'unit_size'           => 'integer',
        'unit_cost_usd'       => 'float',
        'reference_cost_usd'  => 'float',
        'margin_value'        => 'float',
        'credit_type_id'      => 'integer',
        'credit_cost'         => 'integer',
        'overage_credit_cost' => 'integer',
        'is_streaming'        => 'boolean',
        'is_async'            => 'boolean',
        'is_active'           => 'boolean',
    ];

    public function getPricingTypeOptions(): array
    {
        return trans('aero.hub::lang.pricing_types');
    }

    /**
     * Vacío si Aero.Credits no está instalado: el campo simplemente no tiene
     * opciones y el endpoint no puede activarse (ver ProxyController).
     */
    public function getCreditTypeIdOptions(): array
    {
        if (!class_exists(\Aero\Credits\Models\CreditType::class)) {
            return [];
        }

        return \Aero\Credits\Models\CreditType::active()->pluck('label', 'id')->all();
    }

    public function getDivisionOptions(): array
    {
        return [
            'ai_models' => trans('aero.hub::lang.menu.ai_models'),
            'apis'      => trans('aero.hub::lang.menu.apis'),
        ];
    }

    /** Vacío = usar el margen global de Aero\Hub\Models\Settings. */
    public function getMarginTypeOptions(): array
    {
        return [
            ''        => trans('aero.hub::lang.endpoint.margin_use_default'),
            'percent' => trans('aero.hub::lang.settings.margin_type_percent'),
            'fixed'   => trans('aero.hub::lang.settings.margin_type_fixed'),
        ];
    }

    public function scopeDivision($query, string $division)
    {
        return $query->where('division', $division);
    }

    public function actionCode(): string
    {
        return 'hub.' . $this->code;
    }

    /**
     * URL del artículo de documentación generado por
     * Aero\Hub\Classes\DocsSync (mismo slug que usa ese generador). Solo
     * resuelve a una página real cuando el endpoint está activo — el
     * llamador (CatalogSync::publicCatalog()) ya filtra por is_active.
     */
    public function docsUrl(): string
    {
        return url('documentacion/hub-' . $this->code);
    }

    /**
     * Margen efectivo de ESTE endpoint: el propio si cargó ambos campos, si
     * no el default global de Aero\Hub\Models\Settings — así ningún endpoint
     * queda "sin margen" por accidente (siempre hay un valor efectivo).
     *
     * @return array{type: 'percent'|'fixed', value: float}
     */
    public function effectiveMargin(): array
    {
        if ($this->margin_type && $this->margin_value !== null) {
            return ['type' => $this->margin_type, 'value' => (float) $this->margin_value];
        }

        return ['type' => Settings::defaultMarginType(), 'value' => Settings::defaultMarginValue()];
    }

    /**
     * `reference_cost_usd` (lo que cuesta de verdad en YepAPI) + el margen
     * efectivo = precio de venta en USD, convertido a créditos del color
     * elegido (`credit_type_id`) — ceil y mínimo 1 crédito para no regalar
     * el margen por redondeo hacia abajo. Sin costo real conocido ni tipo de
     * crédito elegido, no hay forma de calcular: devuelve null y
     * `recalculateCreditCost()` deja `credit_cost` como esté (manual).
     */
    public function computeCreditCost(): ?int
    {
        if (!$this->reference_cost_usd || !$this->credit_type_id || !class_exists(\Aero\Credits\Models\CreditType::class)) {
            return null;
        }

        $type = \Aero\Credits\Models\CreditType::find($this->credit_type_id);
        if (!$type || (float) $type->usd_value <= 0) {
            return null;
        }

        $margin = $this->effectiveMargin();
        $sellUsd = $margin['type'] === 'fixed'
            ? $this->reference_cost_usd + $margin['value']
            : $this->reference_cost_usd * (1 + $margin['value'] / 100);

        return (int) max(1, ceil($sellUsd / (float) $type->usd_value));
    }

    /**
     * Recalcula y persiste `credit_cost` si hay datos suficientes (ver
     * computeCreditCost()) — se llama sola en beforeSave() cada vez que se
     * toca costo/margen/color, y también en bulk desde
     * HubEndpoints::onRecalculateCosts() para aplicar un cambio en el margen
     * global a todos los endpoints existentes de una sola vez.
     */
    public function recalculateCreditCost(): void
    {
        if (($cost = $this->computeCreditCost()) !== null) {
            $this->credit_cost = $cost;
        }
    }

    public function beforeSave()
    {
        $this->recalculateCreditCost();
    }

    /**
     * Mantiene sincronizados, a partir de esta fila (única fuente de
     * verdad): el espejo en Aero.Credits (ledger/alertas/hints de costo) y
     * el artículo de documentación en Aero.Docs (Aero\Hub\Classes\DocsSync)
     * — ambos opcionales, cada uno con su propio guard por si ese plugin no
     * está instalado.
     */
    public function afterSave()
    {
        if (class_exists(\Aero\Credits\Models\CreditAction::class) && $this->credit_type_id) {
            \Aero\Credits\Models\CreditAction::updateOrCreate(
                ['code' => $this->actionCode()],
                [
                    'label'          => $this->summary ?: $this->code,
                    'plugin'         => 'Aero.Hub',
                    'credit_type_id' => $this->credit_type_id,
                    'default_cost'   => $this->credit_cost,
                    'is_active'      => $this->is_active,
                ]
            );
        }

        \Aero\Hub\Classes\DocsSync::syncOne($this);
    }
}
