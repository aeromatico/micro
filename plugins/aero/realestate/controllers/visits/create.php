<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/realestate/visits') ?>"><?= e("Visitas") ?></a></li><li><?= e($this->pageTitle) ?></li></ul>
<?php Block::endPut() ?>
<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row"><?= $this->formRender() ?></div>
    <div class="form-buttons">
        <button type="submit" data-request="onSave" data-request-data="close:1" data-hotkey="ctrl+enter, cmd+enter" class="btn btn-primary">Programar visita</button>
        <a href="<?= Backend::url('aero/realestate/visits') ?>" class="btn btn-default">Cancelar</a>
    </div>
<?= Form::close() ?>
