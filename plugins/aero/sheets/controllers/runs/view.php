<?php Block::put('breadcrumb') ?>
    <ul>
        <li><a href="<?= Backend::url('aero/sheets/runs') ?>">Historial</a></li>
        <li><?= e($this->pageTitle) ?></li>
    </ul>
<?php Block::endPut() ?>
<div class="padded-container">
    <p><strong><?= e($run->mapping?->name ?? '(mapeo eliminado)') ?></strong> · <?= e($run->direction === 'export' ? 'Sistema → hoja' : 'Hoja → sistema') ?>
       <?= $run->dry_run ? '· prueba' : '' ?> · estado: <strong><?= e($run->status) ?></strong></p>
    <p><?= (int) $run->rows_read ?> leídas · <?= (int) $run->created ?> creadas · <?= (int) $run->updated ?> actualizadas · <?= (int) $run->skipped ?> omitidas · <?= (int) $run->failed ?> con error</p>
    <?php if ($run->errors): ?>
        <ul><?php foreach ((array) $run->errors as $err): ?><li><?= e($err) ?></li><?php endforeach ?></ul>
    <?php endif ?>
</div>
