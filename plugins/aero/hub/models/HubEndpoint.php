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
        'reference_cost_usd', 'credit_type_id', 'credit_cost', 'overage_credit_cost',
        'is_streaming', 'is_async', 'is_active', 'last_synced_at',
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

    public function scopeDivision($query, string $division)
    {
        return $query->where('division', $division);
    }

    public function actionCode(): string
    {
        return 'hub.' . $this->code;
    }

    /**
     * Mantiene sincronizado el catálogo de Aero.Credits con el precio que el
     * superadmin fijó acá — HubEndpoint sigue siendo la única fuente de
     * verdad; esto es solo un espejo para reusar el ledger/alertas/hints de
     * costo que ya existen en toda la plataforma sin código adicional.
     */
    public function afterSave()
    {
        if (!class_exists(\Aero\Credits\Models\CreditAction::class) || !$this->credit_type_id) {
            return;
        }

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
}
