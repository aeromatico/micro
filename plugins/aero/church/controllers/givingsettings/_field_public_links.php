<?php
$links = ['Bienvenida de visitantes' => $model->publicUrl('/bienvenida'), 'Peticiones de oración' => $model->publicUrl('/oracion')];
?>
<?php foreach ($links as $label => $url): ?>
    <div style="display:inline-block;margin:0 25px 15px 0;vertical-align:top">
        <strong><?= e($label) ?></strong><br>
        <?php if ($url): ?>
            <div style="width:160px;height:160px"><?= str_replace('<svg ', '<svg width="160" height="160" ', \Aero\Church\Classes\QrSvg::make($url, 160)) ?></div>
            <small><a href="<?= e($url) ?>" target="_blank" rel="noopener"><?= e($url) ?></a></small>
        <?php else: ?>
            <span class="text-muted">Tu sitio aún no tiene dominio.</span>
        <?php endif ?>
    </div>
<?php endforeach ?>
<p class="text-muted">Imprime el código y colócalo en la entrada. Cada formulario solo funciona cuando lo activas arriba.</p>
