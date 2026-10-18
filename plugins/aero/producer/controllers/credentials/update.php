<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/producer/credentials') ?>">Llaves de proveedores</a></li><li><?= e($this->pageTitle) ?></li></ul>
<?php Block::endPut() ?>
<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row"><?= $this->formRender() ?></div>
    <div class="form-buttons">
        <button type="submit" data-request="onSave" data-request-data="close:1" data-hotkey="ctrl+enter, cmd+enter" class="btn btn-primary">Guardar</button>
        <button type="button" class="btn btn-default" data-request="onTest" data-load-indicator="Probando…">Probar llave</button>
        <button type="button" class="btn btn-danger pull-right" data-request="onDelete" data-request-confirm="¿Eliminar esta llave?">Eliminar</button>
        <a href="<?= Backend::url('aero/producer/credentials') ?>" class="btn btn-default">Cancelar</a>
    </div>
<?= Form::close() ?>
