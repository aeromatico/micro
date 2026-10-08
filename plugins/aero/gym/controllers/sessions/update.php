<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/gym/sessions') ?>"><?= e("Clases") ?></a></li><li><?= e($this->pageTitle) ?></li></ul>
<?php Block::endPut() ?>
<?= $this->makePartial('roster_actions') ?>
<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row"><?= $this->formRender() ?></div>
    <div class="form-buttons">
        <button type="submit" data-request="onSave" data-request-data="close:1" data-hotkey="ctrl+enter, cmd+enter" class="btn btn-primary">Guardar</button>
        <button type="button" class="btn btn-danger pull-right" data-request="onDelete" data-request-confirm="¿Eliminar?">Eliminar</button>
        <a href="<?= Backend::url('aero/gym/sessions') ?>" class="btn btn-default">Cancelar</a>
    </div>
<?= Form::close() ?>
