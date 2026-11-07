<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/clinica/signature') ?>">Mi firma</a></li></ul>
<?php Block::endPut() ?>
<?= \Aero\Clinica\Classes\Hints::render('signature') ?>
<?php if (!$this->fatalError): ?>
<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row"><?= $this->formRender() ?></div>
    <div class="form-buttons">
        <button type="submit" data-request="onSave" data-hotkey="ctrl+enter, cmd+enter" class="btn btn-primary">Guardar</button>
    </div>
<?= Form::close() ?>
<?php else: ?>
    <p class="flash-message static error"><?= e($this->fatalError) ?></p>
<?php endif ?>
