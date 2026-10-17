<?php
/** @var \Aero\Medical\Models\Professional $formModel */
$items = ['signature' => ['Firma', 'Imagen de su firma (fondo blanco o transparente).'], 'seal' => ['Sello', 'Imagen del sello. Es una imagen para la impresión, no una firma electrónica certificada.']];
?>
<?php if (!$formModel->exists): ?>
    <p class="text-muted">Guarde primero sus datos para subir la firma y el sello.</p>
<?php else: ?>
<div style="display:flex;gap:24px;flex-wrap:wrap">
<?php foreach ($items as $which => [$label, $help]): $uri = $formModel->imageDataUri($which); ?>
    <div style="min-width:260px">
        <label style="font-weight:600;display:block;margin-bottom:6px"><?= e($label) ?></label>
        <div style="border:1px dashed rgba(128,128,128,.5);padding:8px;min-height:90px;background:#fff;display:flex;align-items:center;justify-content:center">
            <?php if ($uri): ?><img src="<?= $uri ?>" alt="<?= e($label) ?>" style="max-height:100px;max-width:240px"><?php else: ?><span style="color:#777">Sin imagen</span><?php endif ?>
        </div>
        <p class="help-block" style="margin:6px 0"><?= e($help) ?> Se guarda cifrada, no como archivo.</p>
        <input type="file" name="<?= e($which) ?>_file" accept="image/png,image/jpeg" id="img-<?= e($which) ?>"
               data-request="onUploadImage" data-request-data="which: '<?= e($which) ?>'" data-request-files>
        <?php if ($uri): ?>
            <button type="button" class="btn btn-default btn-sm" style="margin-top:6px" data-request="onRemoveImage" data-request-data="which: '<?= e($which) ?>'"
                    data-request-confirm="¿Quitar esta imagen?">Quitar</button>
        <?php endif ?>
    </div>
<?php endforeach ?>
</div>
<?php endif ?>
