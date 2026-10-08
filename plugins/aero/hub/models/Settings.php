<?php namespace Aero\Hub\Models;

use Model;

/**
 * Margen de ganancia por defecto para el catálogo de Hub (Modelos IA + APIs):
 * se aplica sobre `reference_cost_usd` de cada HubEndpoint que no tenga su
 * propio margin_type/margin_value cargado (ver HubEndpoint::effectiveMargin()
 * y ::recalculateCreditCost()). Cambiar esto acá NO recalcula solo: los
 * endpoints existentes se actualizan con el botón "Recalcular costos" del
 * listado (HubEndpoints::onRecalculateCosts()).
 */
class Settings extends Model
{
    public $implement = ['System.Behaviors.SettingsModel'];

    public $settingsCode = 'aero_hub_settings';

    public $settingsFields = 'fields.yaml';

    public static function defaultMarginType(): string
    {
        $type = (string) self::get('default_margin_type', 'percent');

        return $type === 'fixed' ? 'fixed' : 'percent';
    }

    public static function defaultMarginValue(): float
    {
        return (float) self::get('default_margin_value', 30);
    }

    public function getDefaultMarginTypeOptions(): array
    {
        return [
            'percent' => trans('aero.hub::lang.settings.margin_type_percent'),
            'fixed'   => trans('aero.hub::lang.settings.margin_type_fixed'),
        ];
    }
}
