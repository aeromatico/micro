<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/school/feeplans') ?>"><?= e("Conceptos de cobro") ?></a></li><li><?= e($this->pageTitle) ?></li></ul>
<?php Block::endPut() ?>
<?php if ($formModel->exists): ?>
<div class="callout callout-info" style="margin-bottom:15px">
    <div class="header"><h3>Aplica a <?= $formModel->targetStudents()->count() ?> estudiantes inscritos · cuotas ya generadas: <?= \Aero\School\Models\Charge::where('fee_plan_id', $formModel->id)->count() ?></h3></div>
    <div class="content">
        <button type="button" class="btn btn-sm btn-primary" data-request="onGenerate"
            data-request-confirm="¿Generar las cuotas de este concepto? No se duplican las que ya existen.">Generar cuotas</button>
        <span class="text-muted">También se generan solas al inscribir un alumno nuevo.</span>
    </div>
</div>
<?php endif ?>
<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row"><?= $this->formRender() ?></div>
    <div class="form-buttons">
        <button type="submit" data-request="onSave" data-request-data="close:1" data-hotkey="ctrl+enter, cmd+enter" class="btn btn-primary">Guardar</button>
        <button type="button" class="btn btn-danger pull-right" data-request="onDelete" data-request-confirm="¿Eliminar?">Eliminar</button>
        <a href="<?= Backend::url('aero/school/feeplans') ?>" class="btn btn-default">Cancelar</a>
    </div>
<?= Form::close() ?>
