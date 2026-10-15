<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/school/subjects') ?>"><?= e("Áreas") ?></a></li><li><?= e($this->pageTitle) ?></li></ul>
<?php Block::endPut() ?>
<?= Form::open(['class' => 'school-form']) ?>
    <div><?= $this->formRender() ?></div>
    <div class="form-buttons">
        <button type="submit" data-request="onSave" data-request-data="close:1" data-hotkey="ctrl+enter, cmd+enter" class="btn btn-primary">Guardar</button>
        <a href="<?= Backend::url('aero/school/subjects') ?>" class="btn btn-default">Cancelar</a>
    </div>
<?= Form::close() ?>
