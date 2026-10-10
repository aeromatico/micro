<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/office/branches') ?>"><?= e("Sucursales") ?></a></li><li><?= e($this->pageTitle) ?></li></ul>
<?php Block::endPut() ?>
<?php if (!$this->fatalError): ?>
<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row"><?= $this->formRender() ?></div>
    <div class="form-buttons">
        <button type="submit" data-request="onSave" data-request-data="close:1" data-hotkey="ctrl+enter, cmd+enter" class="btn btn-primary">Guardar</button>
        <button type="button" class="btn btn-danger pull-right" data-request="onDelete" data-request-confirm="¿Eliminar? Si tiene reservas, mejor desactívalo.">Eliminar</button>
        <a href="<?= Backend::url('aero/office/branches') ?>" class="btn btn-default">Cancelar</a>
    </div>
<?= Form::close() ?>
<?php else: ?>
    <p class="flash-message static error"><?= e($this->fatalError) ?></p>
<?php endif ?>
