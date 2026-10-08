<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/finance/movements') ?>">Ingresos y egresos</a></li><li><?= e($this->pageTitle) ?></li></ul>
<?php Block::endPut() ?>
<?= Form::open(['class' => 'layout']) ?>
    <?php if ($formModel->status === 'void'): ?><p class="flash-message static error">Movimiento anulado.</p><?php endif ?>
    <div class="layout-row"><?= $this->formRenderPreview() ?></div>
    <div class="form-buttons">
        <?php if ($formModel->status === 'posted'): ?>
            <button type="button" class="btn btn-danger" data-request="onVoid" data-request-confirm="¿Anular este movimiento? Se creará el asiento inverso.">Anular</button>
        <?php endif ?>
        <a href="<?= Backend::url('aero/finance/movements') ?>" class="btn btn-default">Volver</a>
    </div>
<?= Form::close() ?>
