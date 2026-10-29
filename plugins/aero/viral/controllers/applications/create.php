<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/viral/applications') ?>">Postulaciones</a></li><li><?= e($this->pageTitle) ?></li></ul>
<?php Block::endPut() ?>
<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row"><?= $this->formRender() ?></div>
    <div class="form-buttons">
        <button type="submit" data-request="onSave" data-request-data="close:1" class="btn btn-primary">Guardar</button>
        <a href="<?= Backend::url('aero/viral/applications') ?>" class="btn btn-default">Cancelar</a>
    </div>
<?= Form::close() ?>
