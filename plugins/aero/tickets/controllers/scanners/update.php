<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/tickets/scanners') ?>">Escáneres</a></li><li><?= e($this->pageTitle) ?></li></ul>
<?php Block::endPut() ?>
<?php if (!$this->fatalError): ?>
<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row"><?= $this->formRender() ?></div>
    <div class="form-buttons">
        <button type="submit" data-request="onSave" data-request-data="close:1" data-hotkey="ctrl+enter, cmd+enter" class="btn btn-primary">Guardar</button>
    <?php if ($formModel->exists): ?>
        <button type="button" class="btn btn-warning" data-request="onRegenerate" data-request-data="id: <?= (int) $formModel->id ?>"
                data-request-confirm="¿Generar un enlace nuevo? El actual dejará de funcionar.">Generar enlace nuevo</button>
        <button type="button" class="btn btn-default" data-request="onToggle" data-request-data="id: <?= (int) $formModel->id ?>"><?= $formModel->is_active ? 'Desactivar' : 'Activar' ?></button>
        <button type="button" class="btn btn-danger pull-right" data-request="onDelete" data-request-confirm="¿Eliminar este escáner? Su registro de lecturas se conserva.">Eliminar</button>
    <?php endif ?>
        <a href="<?= Backend::url('aero/tickets/scanners') ?>" class="btn btn-default">Volver</a>
    </div>
<?= Form::close() ?>
<?php else: ?>
    <p class="flash-message static error"><?= e($this->fatalError) ?></p>
<?php endif ?>
