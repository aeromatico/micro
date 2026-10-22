<?php
// Selector de mapa reutilizable de Aero.Tracking (Leaflet + OpenStreetMap, con vista satélite). Dependencia blanda:
// sin él los campos de latitud y longitud siguen funcionando a mano.
$hasPicker = file_exists(plugins_path('aero/tracking/assets/js/locationpicker.js'));
$loc = $formModel->location;
$hasPoint = $formModel->lat !== null && $formModel->lng !== null && $formModel->lat !== '' && $formModel->lng !== '';
if ($hasPicker) {
    $this->addCss('/plugins/aero/tracking/assets/css/locationpicker.css');
    $this->addJs('/plugins/aero/tracking/assets/js/locationpicker.js');
}
?>
<?php if ($hasPicker): ?>
    <div data-aero-location-picker
         data-lat-input='[name="Property[lat]"]' data-lng-input='[name="Property[lng]"]'
         <?php if ($hasPoint): ?>data-lat="<?= e($formModel->lat) ?>" data-lng="<?= e($formModel->lng) ?>"<?php endif ?>
         data-default-lat="<?= e($loc?->lat ?? -17.78) ?>" data-default-lng="<?= e($loc?->lng ?? -63.18) ?>"
         data-zoom="<?= $hasPoint ? 17 : ($loc?->lat ? 14 : 12) ?>" data-height="360" data-autolocate="0"></div>
    <p class="help-block">Si la propiedad aún no tiene punto, el mapa se centra en la zona elegida. Guarda para que quede registrado.</p>
<?php else: ?>
    <p class="text-muted">Escribe la latitud y la longitud en los campos de arriba.</p>
<?php endif ?>
