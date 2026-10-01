<?php namespace Aero\Tracking\Components;

use Cms\Classes\ComponentBase;

/**
 * Mapa para que el visitante fije su ubicación exacta (detecta por GPS y deja
 * ajustar el pin). Escribe en dos inputs del formulario de la página:
 *
 *   {% component 'locationPicker' latInput='#lat' lngInput='#lng' %}
 *
 * Para usarlo sin componente (p.ej. dentro de otro componente), basta
 * `LocationPicker::assetTags()` y un `<div data-aero-location-picker ...>`.
 */
class LocationPicker extends ComponentBase
{
    public function componentDetails(): array
    {
        return [
            'name'        => 'Selector de ubicación',
            'description' => 'Mapa con detección de ubicación y pin ajustable.',
        ];
    }

    public function defineProperties(): array
    {
        return [
            'latInput'   => ['title' => 'Selector del input de latitud', 'default' => '#lat'],
            'lngInput'   => ['title' => 'Selector del input de longitud', 'default' => '#lng'],
            'accInput'   => ['title' => 'Selector del input de precisión (opcional)', 'default' => ''],
            'height'     => ['title' => 'Alto (px)', 'default' => '320'],
            'autolocate' => ['title' => 'Detectar al cargar', 'type' => 'checkbox', 'default' => true],
        ];
    }

    public function onRun()
    {
        $this->page['aeroLocationPickerAssets'] = static::assetTags();
    }

    /** <link> + <script> con ?v= por fecha del archivo (Cloudflare cachea assets un año). */
    public static function assetTags(): string
    {
        $base = plugins_path('aero/tracking/assets');
        $v = fn ($f) => @filemtime("{$base}/{$f}") ?: 1;

        return '<link rel="stylesheet" href="/plugins/aero/tracking/assets/css/locationpicker.css?v=' . $v('css/locationpicker.css') . '">'
            . '<script defer src="/plugins/aero/tracking/assets/js/locationpicker.js?v=' . $v('js/locationpicker.js') . '"></script>';
    }
}
