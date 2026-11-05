<?php
// Selector de mapa de Aero.Tracking (Leaflet + OSM). Dependencia blanda: sin él, latitud y longitud a mano.
$hasPicker = file_exists(plugins_path('aero/tracking/assets/js/locationpicker.js'));
$hasPoint = $formModel->lat !== null && $formModel->lng !== null && $formModel->lat !== '' && $formModel->lng !== '';
if ($hasPicker) {
    $this->addCss('/plugins/aero/tracking/assets/css/locationpicker.css');
    $this->addJs('/plugins/aero/tracking/assets/js/locationpicker.js');
}
?>
<?php if ($hasPicker): ?>
    <div data-aero-location-picker
         data-lat-input='[name="Event[lat]"]' data-lng-input='[name="Event[lng]"]'
         <?php if ($hasPoint): ?>data-lat="<?= e($formModel->lat) ?>" data-lng="<?= e($formModel->lng) ?>"<?php endif ?>
         data-default-lat="-16.5" data-default-lng="-64.0"
         data-zoom="<?= $hasPoint ? 17 : 5 ?>" data-height="360" data-autolocate="0"></div>
<?php else: ?>
    <p class="text-muted">Escribe la latitud y la longitud en los campos de arriba.</p>
<?php endif ?>
