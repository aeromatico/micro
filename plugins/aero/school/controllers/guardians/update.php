<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/school/guardians') ?>"><?= e("Apoderados") ?></a></li><li><?= e($this->pageTitle) ?></li></ul>
<?php Block::endPut() ?>
<?php if ($formModel->exists): ?>
<div class="callout callout-<?= $formModel->user_id ? 'success' : 'warning' ?>" style="margin-bottom:15px">
    <div class="header"><h3>Portal del padre: <?= $formModel->user_id ? 'activo' : 'sin acceso' ?></h3></div>
    <div class="content">
        <?php if (!$formModel->user_id): ?>
            <button type="button" class="btn btn-sm btn-primary" data-request="onCreateUser"
                data-request-confirm="¿Crear el acceso al portal con el correo <?= e($formModel->email) ?>?">Crear acceso al portal</button>
        <?php else: ?>Hijos: <?= e($formModel->students->pluck('full_name')->implode(', ')) ?><?php endif ?>
    </div>
</div>
<?php endif ?>
<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row"><?= $this->formRender() ?></div>
    <div class="form-buttons">
        <button type="submit" data-request="onSave" data-request-data="close:1" data-hotkey="ctrl+enter, cmd+enter" class="btn btn-primary">Guardar</button>
        <button type="button" class="btn btn-danger pull-right" data-request="onDelete" data-request-confirm="¿Eliminar?">Eliminar</button>
        <a href="<?= Backend::url('aero/school/guardians') ?>" class="btn btn-default">Cancelar</a>
    </div>
<?= Form::close() ?>
