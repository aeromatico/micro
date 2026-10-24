<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/producer/orders') ?>">Mis videos</a></li><li><?= e($this->pageTitle) ?></li></ul>
<?php Block::endPut() ?>
<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row"><?= $this->formRender() ?></div>
    <div class="form-buttons">
        <button type="submit" data-request="onSave" data-hotkey="ctrl+enter, cmd+enter" class="btn btn-primary">Guardar borrador</button>
        <a href="<?= Backend::url('aero/producer/orders') ?>" class="btn btn-default">Cancelar</a>
    </div>
<?= Form::close() ?>
