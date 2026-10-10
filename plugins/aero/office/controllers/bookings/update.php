<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/office/bookings') ?>"><?= e("Reservas") ?></a></li><li><?= e($this->pageTitle) ?></li></ul>
<?php Block::endPut() ?>
<?php if (!$this->fatalError): ?>
<?= $this->makePartial('detail', ['b' => $formModel]) ?>
<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row"><?= $this->formRender() ?></div>
    <div class="form-buttons">
        <button type="submit" data-request="onSave" data-hotkey="ctrl+enter, cmd+enter" class="btn btn-primary">Guardar notas</button>
        <a href="<?= Backend::url('aero/office/bookings') ?>" class="btn btn-default">Volver</a>
    </div>
<?= Form::close() ?>
<?php else: ?>
    <p class="flash-message static error"><?= e($this->fatalError) ?></p>
<?php endif ?>
