<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/school/students') ?>"><?= e("Estudiantes") ?></a></li><li><?= e($this->pageTitle) ?></li></ul>
<?php Block::endPut() ?>
<?php if ($formModel->exists): ?>
<div class="callout callout-info" style="margin-bottom:15px">
    <div class="header"><h3><?= e($formModel->full_name) ?> — saldo por pagar: <?= number_format($formModel->pendingBalance(), 2) ?>
        <?php if ($formModel->overdueBalance() > 0): ?><span class="text-danger">(vencido <?= number_format($formModel->overdueBalance(), 2) ?>)</span><?php endif ?></h3></div>
    <div class="content">
        <a href="<?= Backend::url('aero/school/collect/student/' . $formModel->id) ?>" class="btn btn-sm btn-primary">Estado de cuenta / Cobrar</a>
        <button type="button" class="btn btn-sm btn-default" data-request="onGenerateCharges">Generar cuotas faltantes</button>
        <?php foreach ($formModel->guardians as $g): ?>
            <span class="label label-default" style="margin-left:8px">
                <?= e($g->name) ?><?= $g->pivot->relationship ? ' (' . e($g->pivot->relationship) . ')' : '' ?> · <?= e($g->phone ?: 'sin celular') ?>
                <a href="#" data-request="onRemoveGuardian" data-request-data="guardian_id: <?= (int) $g->id ?>" data-request-confirm="¿Desvincular a <?= e($g->name) ?>?" title="Desvincular">×</a>
            </span>
        <?php endforeach ?>
    </div>
</div>
<?php endif ?>
<?= Form::open(['class' => 'school-form']) ?>
    <div><?= $this->formRender() ?></div>
    <div class="form-buttons">
        <button type="submit" data-request="onSave" data-request-data="close:1" data-hotkey="ctrl+enter, cmd+enter" class="btn btn-primary">Guardar</button>
        <button type="button" class="btn btn-danger pull-right" data-request="onDelete" data-request-confirm="¿Eliminar?">Eliminar</button>
        <a href="<?= Backend::url('aero/school/students') ?>" class="btn btn-default">Cancelar</a>
    </div>
<?= Form::close() ?>
