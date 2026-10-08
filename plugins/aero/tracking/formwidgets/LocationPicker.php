<?php namespace Aero\Tracking\FormWidgets;

use Backend\Classes\FormWidgetBase;

/**
 * Mapa para fijar una ubicación exacta en cualquier formulario del backend.
 *
 *   lat:
 *       label: Ubicación
 *       type: locationpicker
 *       lngField: lng          # campo hermano que guarda la longitud (oculto)
 *       accuracyField: acc     # opcional
 *   lng:
 *       type: text
 *       containerAttributes: {style: 'display:none'}
 *   # No usar `hidden: true` ni type 'hidden': October purga los campos hidden al guardar.
 *
 * Funciona también dentro de repeaters (los inputs hermanos se resuelven
 * por el arrayName del campo).
 */
class LocationPicker extends FormWidgetBase
{
    protected $defaultAlias = 'locationpicker';

    public $lngField = 'lng';
    public $accuracyField = null;
    public $height = 320;

    public function init()
    {
        $this->fillFromConfig(['lngField', 'accuracyField', 'height']);
    }

    public function render()
    {
        $this->prepareVars();

        return $this->makePartial('locationpicker');
    }

    protected function prepareVars()
    {
        $this->vars['name'] = $this->formField->getName();
        $this->vars['value'] = $this->getLoadValue();
        $this->vars['height'] = (int) $this->height;
        $this->vars['lngSelector'] = $this->siblingSelector($this->lngField);
        $this->vars['accSelector'] = $this->accuracyField ? $this->siblingSelector($this->accuracyField) : null;
        $this->vars['lng'] = $this->model->{$this->lngField} ?? null;
    }

    protected function siblingSelector(string $field): string
    {
        $prefix = $this->formField->arrayName;

        return '[name="' . ($prefix ? "{$prefix}[{$field}]" : $field) . '"]';
    }

    public function loadAssets()
    {
        $this->addCss('/plugins/aero/tracking/assets/css/locationpicker.css', 'Aero.Tracking');
        $this->addJs('/plugins/aero/tracking/assets/js/locationpicker.js', 'Aero.Tracking');
    }

    public function getSaveValue($value)
    {
        return $value === '' || $value === null ? null : $value;
    }
}
