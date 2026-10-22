<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/realestate/clients') ?>"><?= e("Clientes") ?></a></li><li><?= e($this->pageTitle) ?></li></ul>
<?php Block::endPut() ?>
<?php if (!$this->fatalError): ?>
<?php if (!$formModel->user_id): ?>
    <p><button type="button" class="btn btn-default" data-request="onCreateUser" data-request-data="id: <?= (int) $formModel->id ?>" data-request-confirm="¿Crear (o vincular) el usuario con este correo?">Crear usuario de acceso</button></p>
<?php else: ?>
    <p class="text-muted">Usuario vinculado (RainLab.User #<?= (int) $formModel->user_id ?>).</p>
<?php endif ?>
<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row"><?= $this->formRender() ?></div>
    <div class="form-buttons">
        <button type="submit" data-request="onSave" data-request-data="close:1" data-hotkey="ctrl+enter, cmd+enter" class="btn btn-primary">Guardar</button>
        <button type="button" class="btn btn-danger pull-right" data-request="onDelete" data-request-confirm="¿Eliminar este registro?">Eliminar</button>
        <a href="<?= Backend::url('aero/realestate/clients') ?>" class="btn btn-default">Cancelar</a>
    </div>
<?= Form::close() ?>
<?php else: ?>
    <p class="flash-message static error"><?= e($this->fatalError) ?></p>
<?php endif ?>
