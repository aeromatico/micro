<?php $h = $this->hintVars(); ?>
<?php if (!$h['hasAccounts']): ?>
    <p class="flash-message static warning">
        Aún no tienes redes conectadas. <a href="<?= Backend::url('aero/social/connections') ?>">Conéctalas aquí</a> y vuelve.
    </p>
<?php elseif ($h['tariffs']): ?>
    <p class="text-muted" style="margin:0 0 8px">
        Costo por publicación:
        <?php foreach ($h['tariffs'] as $net => $label): ?>
            <strong><?= e(\Aero\Social\Classes\Networks::label($net)) ?></strong> <?= e($label) ?><?= $net !== array_key_last($h['tariffs']) ? ' · ' : '' ?>
        <?php endforeach ?>
        — si una red falla, se devuelve.
    </p>
<?php endif ?>
