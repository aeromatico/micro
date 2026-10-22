<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/realestate/commissions') ?>"><?= e("Comisiones") ?></a></li><li><?= e($this->pageTitle) ?></li></ul>
<?php Block::endPut() ?>
<?php if (!$this->fatalError): ?>
<?= $this->makePartial('summary', ['m' => $formModel]) ?>
<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row"><?= $this->formRender() ?></div>
    <div class="form-buttons">
        <button type="submit" data-request="onSave" data-request-data="close:1" data-hotkey="ctrl+enter, cmd+enter" class="btn btn-primary">Guardar</button>
        <a href="<?= Backend::url('aero/realestate/commissions') ?>" class="btn btn-default">Cancelar</a>
    </div>
<?= Form::close() ?>
<?php else: ?>
    <p class="flash-message static error"><?= e($this->fatalError) ?></p>
<?php endif ?>
