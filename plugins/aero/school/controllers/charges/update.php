<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/school/charges') ?>"><?= e("Cuotas") ?></a></li><li><?= e($this->pageTitle) ?></li></ul>
<?php Block::endPut() ?>
<?php if ($formModel->exists && $formModel->status !== 'void'): ?>
<div class="callout callout-info" style="margin-bottom:15px">
    <div class="header"><h3>Saldo: <?= number_format($formModel->due, 2) ?> · pagado <?= number_format($formModel->paid_amount, 2) ?></h3></div>
    <div class="content">
        <a href="<?= Backend::url('aero/school/collect/student/' . $formModel->student_id) ?>" class="btn btn-sm btn-primary">Cobrar</a>
        <?php if ($formModel->paid_amount <= 0): ?>
        <button type="button" class="btn btn-sm btn-danger" data-request="onVoid" data-request-confirm="¿Anular esta cuota?">Anular cuota</button>
        <?php endif ?>
    </div>
</div>
<?php endif ?>
<?= Form::open(['class' => 'school-form']) ?>
    <div><?= $this->formRender() ?></div>
    <div class="form-buttons">
        <button type="submit" data-request="onSave" data-request-data="close:1" data-hotkey="ctrl+enter, cmd+enter" class="btn btn-primary">Guardar</button>
        <button type="button" class="btn btn-danger pull-right" data-request="onDelete" data-request-confirm="¿Eliminar?">Eliminar</button>
        <a href="<?= Backend::url('aero/school/charges') ?>" class="btn btn-default">Cancelar</a>
    </div>
<?= Form::close() ?>
