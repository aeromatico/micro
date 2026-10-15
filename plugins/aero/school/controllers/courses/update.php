<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/school/courses') ?>"><?= e("Cursos") ?></a></li><li><?= e($this->pageTitle) ?></li></ul>
<?php Block::endPut() ?>
<?php if ($formModel->exists): ?>
<div class="callout callout-info" style="margin-bottom:15px">
    <div class="header"><i class="icon-info"></i><h3>Áreas de este curso: <?= $formModel->subjects()->count() ?></h3></div>
    <div class="content">
        <button type="button" class="btn btn-sm btn-primary" data-request="onSeedSubjects"
            data-request-confirm="¿Cargar las áreas del currículo base de <?= e($formModel->level_label) ?>? No se duplican las que ya existen.">Cargar áreas del currículo base</button>
        <a href="<?= Backend::url('aero/school/subjects?course_id=' . $formModel->id) ?>" class="btn btn-sm btn-default">Ver / editar áreas</a>
    </div>
</div>
<?php endif ?>
<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row"><?= $this->formRender() ?></div>
    <div class="form-buttons">
        <button type="submit" data-request="onSave" data-request-data="close:1" data-hotkey="ctrl+enter, cmd+enter" class="btn btn-primary">Guardar</button>
        <button type="button" class="btn btn-danger pull-right" data-request="onDelete" data-request-confirm="¿Eliminar?">Eliminar</button>
        <a href="<?= Backend::url('aero/school/courses') ?>" class="btn btn-default">Cancelar</a>
    </div>
<?= Form::close() ?>
