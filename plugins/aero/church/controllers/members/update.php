<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/church/members') ?>"><?= e("Miembros") ?></a></li><li><?= e($this->pageTitle) ?></li></ul>
<?php Block::endPut() ?>
<?php if (!$this->fatalError): ?>
<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row"><?= $this->formRender() ?></div>
    <div class="form-buttons">
        <button type="submit" data-request="onSave" data-request-data="close:1" data-hotkey="ctrl+enter, cmd+enter" class="btn btn-primary">Guardar</button>
        <button type="button" class="btn btn-danger pull-right" data-request="onDelete" data-request-confirm="¿Eliminar este registro?">Eliminar</button>
        <a href="<?= Backend::url('aero/church/members') ?>" class="btn btn-default">Cancelar</a>
    </div>
<?= Form::close() ?>
<div class="form-group" style="margin-top:20px">
    <?php if ($formModel->user_id): ?>
        <p class="text-muted">Tiene acceso al portal del miembro (usuario #<?= (int) $formModel->user_id ?>).</p>
    <?php else: ?>
        <button type="button" class="btn btn-default" data-request="onCreatePortalUser" data-request-confirm="¿Crear su acceso al portal con su correo?">Crear acceso al portal</button>
        <span class="text-muted">Necesita un correo y ser mayor de edad.</span>
    <?php endif ?>
</div>
<?php else: ?>
    <p class="flash-message static error"><?= e($this->fatalError) ?></p>
<?php endif ?>
