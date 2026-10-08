<input type="hidden" name="<?= e($name) ?>" id="<?= $this->getId('lat') ?>" value="<?= e($value) ?>">
<div data-aero-location-picker
     data-lat-input="#<?= $this->getId('lat') ?>"
     data-lng-input='<?= e($lngSelector) ?>'
     <?php if ($accSelector): ?>data-acc-input='<?= e($accSelector) ?>'<?php endif ?>
     <?php if ($value !== null && $value !== '' && $lng !== null): ?>data-lat="<?= e($value) ?>" data-lng="<?= e($lng) ?>"<?php endif ?>
     data-height="<?= $height ?>"></div>
