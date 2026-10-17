<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/medical/patients') ?>"><?= e("Pacientes") ?></a></li><li><?= e($this->pageTitle) ?></li></ul>
<?php Block::endPut() ?>
<?php if (!$this->fatalError): ?>
<?php if (BackendAuth::getUser()->hasAccess(['aero.medical.professional', 'aero.medical.superadmin'], false)): ?>
<?= $this->makePartial('ficha', ['p' => $formModel]) ?>
<details style="margin-bottom:16px"><summary style="cursor:pointer;font-weight:600;margin-bottom:8px">Datos del paciente</summary>
<?php endif ?>
<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row"><?= $this->formRender() ?></div>
    <div class="form-buttons">
        <button type="submit" data-request="onSave" data-request-data="close:1" data-hotkey="ctrl+enter, cmd+enter" class="btn btn-primary">Guardar</button>
        <button type="button" class="btn btn-danger pull-right" data-request="onDelete" data-request-confirm="¿Eliminar este paciente? Queda en la bitácora.">Eliminar</button>
        <a href="<?= Backend::url('aero/medical/patients') ?>" class="btn btn-default">Cancelar</a>
    </div>
<?= Form::close() ?>
<?php if (BackendAuth::getUser()->hasAccess(['aero.medical.professional', 'aero.medical.superadmin'], false)): ?></details><?php endif ?>
<?php else: ?>
    <p class="flash-message static error"><?= e($this->fatalError) ?></p>
<?php endif ?>
