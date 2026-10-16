<?php Block::put('breadcrumb') ?>
    <ul>
        <li><a href="<?= Backend::url('aero/invoicing/invoices') ?>">Facturas</a></li>
        <li><?= e($this->pageTitle) ?></li>
    </ul>
<?php Block::endPut() ?>

<?php if (!$this->fatalError): ?>
    <?php $inv = $formModel ?>
    <?php if ($inv->status !== 'draft'): ?>
        <p class="flash-message static <?= in_array($inv->status, ['validated', 'received']) ? 'success' : (in_array($inv->status, ['rejected', 'error']) ? 'error' : 'info') ?>">
            <?= e($inv->statusLabel) ?>
            <?php if ($inv->number): ?> · N.º <?= e($inv->number) ?><?php endif ?>
            <?php if ($inv->error): ?> · <?= e($inv->error) ?><?php endif ?>
            <?php foreach ((array) $inv->messages as $m): ?> · <?= e(is_scalar($m) ? $m : json_encode($m)) ?><?php endforeach ?>
        </p>
    <?php endif ?>

    <?= Form::open(['class' => 'layout']) ?>
        <div class="layout-row"><?= $this->formRender() ?></div>
        <div class="form-buttons">
            <?php if ($inv->isEditable): ?>
                <button type="submit" data-request="onSave" data-request-data="redirect:0" data-hotkey="ctrl+s, cmd+s" data-load-indicator="Guardando…" class="btn btn-default">Guardar borrador</button>
                <button type="button" class="btn btn-primary"
                    data-request="onIssue" data-request-data="id: <?= (int) $inv->id ?>"
                    data-request-confirm="¿Emitir esta factura ante el SIN? Es un documento fiscal."
                    data-load-indicator="Emitiendo…">Emitir factura</button>
            <?php endif ?>
            <?php if ($inv->isVoidable): ?>
                <button type="button" class="btn btn-danger"
                    data-request="onVoid" data-request-data="id: <?= (int) $inv->id ?>, reason: 1"
                    data-request-confirm="¿Anular esta factura? No se puede deshacer."
                    data-load-indicator="Anulando…">Anular factura</button>
            <?php endif ?>
            <a href="<?= Backend::url('aero/invoicing/invoices') ?>" class="btn btn-default">Volver</a>
        </div>
    <?= Form::close() ?>
<?php else: ?>
    <p class="flash-message static error"><?= e($this->fatalError) ?></p>
<?php endif ?>
