<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/medical/signature') ?>">Mi firma</a></li></ul>
<?php Block::endPut() ?>
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
