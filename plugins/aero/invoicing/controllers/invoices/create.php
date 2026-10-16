<?php Block::put('breadcrumb') ?>
    <ul>
        <li><a href="<?= Backend::url('aero/invoicing/invoices') ?>">Facturas</a></li>
        <li><?= e($this->pageTitle) ?></li>
    </ul>
<?php Block::endPut() ?>

<?php if (!$this->fatalError): ?>
    <?= Form::open(['class' => 'layout']) ?>
        <div class="layout-row"><?= $this->formRender() ?></div>
        <div class="form-buttons">
            <button type="submit" data-request="onSave" data-request-data="redirect:0" data-hotkey="ctrl+s, cmd+s" data-load-indicator="Guardando…" class="btn btn-primary">Crear</button>
            <button type="button" data-request="onSave" data-load-indicator="Guardando…" class="btn btn-default">Crear y cerrar</button>
            <a href="<?= Backend::url('aero/invoicing/invoices') ?>" class="btn btn-default">Cancelar</a>
        </div>
    <?= Form::close() ?>
<?php else: ?>
    <p class="flash-message static error"><?= e($this->fatalError) ?></p>
<?php endif ?>
