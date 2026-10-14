<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/community/posts') ?>">Comunidad</a></li><li><?= e($formModel->title) ?></li></ul>
<?php Block::endPut() ?>
<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row"><?= $this->makePartial('thread', ['post' => $formModel]) ?></div>
<?= Form::close() ?>

<?php if (\Aero\Community\Classes\CurrentTenant::isAdmin()): ?>
    <?= Form::open(['class' => 'layout']) ?>
        <div class="layout-row"><?= $this->formRender() ?></div>
        <div class="form-buttons">
            <button type="submit" data-request="onSave" data-request-data="close:1" data-hotkey="ctrl+enter, cmd+enter" class="btn btn-primary">Guardar respuesta</button>
        </div>
    <?= Form::close() ?>
<?php endif ?>
