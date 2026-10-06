<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/tracking/feeds') ?>">Seguimiento externo</a></li><li><?= e($this->pageTitle) ?></li></ul>
<?php Block::endPut() ?>
<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row"><?= $this->formRender() ?></div>
    <div class="form-buttons">
        <button type="submit" data-request="onSave" data-hotkey="ctrl+enter, cmd+enter" class="btn btn-primary">Guardar nombre</button>
        <?php if ($formModel->isActive()): ?>
            <button type="button" class="btn btn-default" data-request="onPollNow">Actualizar ahora</button>
            <button type="button" class="btn btn-danger pull-right" data-request="onStop" data-request-confirm="¿Dejar de seguir este enlace?">Dejar de seguir</button>
        <?php endif ?>
        <a href="<?= Backend::url('aero/tracking/feeds') ?>" class="btn btn-default">Volver</a>
    </div>
<?= Form::close() ?>
