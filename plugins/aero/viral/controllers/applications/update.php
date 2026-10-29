<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/viral/applications') ?>">Postulaciones</a></li><li><?= e($this->pageTitle) ?></li></ul>
<?php Block::endPut() ?>
<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row"><?= $this->formRender() ?></div>
    <div class="form-buttons">
        <button type="submit" data-request="onSave" data-request-data="close:1" data-hotkey="ctrl+enter, cmd+enter" class="btn btn-primary">Guardar</button>
        <button type="button" class="btn btn-default" data-request="onInReview">En revisión</button>
        <button type="button" class="btn btn-success" data-request="onApprove" data-request-confirm="¿Aprobar? Requiere identidad verificada.">Aprobar</button>
        <button type="button" class="btn btn-warning" data-request="onRequestChanges">Pedir cambios</button>
        <button type="button" class="btn btn-danger" data-request="onReject" data-request-confirm="¿Rechazar esta postulación?">Rechazar</button>
        <a href="<?= Backend::url('aero/viral/applications') ?>" class="btn btn-default pull-right">Volver</a>
    </div>
<?= Form::close() ?>
