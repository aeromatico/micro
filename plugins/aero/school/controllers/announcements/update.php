<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/school/announcements') ?>"><?= e("Comunicados") ?></a></li><li><?= e($this->pageTitle) ?></li></ul>
<?php Block::endPut() ?>
<?php if ($formModel->exists): ?>
<?php $count = app(\Aero\School\Classes\Announcer::class)->recipients($formModel)->count(); ?>
<div class="callout callout-<?= $formModel->status === 'sent' ? 'success' : 'info' ?>" style="margin-bottom:15px">
    <div class="header"><h3><?= $formModel->status === 'sent'
        ? 'Enviado: ' . (int) $formModel->delivered . ' entregados, ' . (int) $formModel->failed . ' fallidos de ' . (int) $formModel->total
        : ($formModel->status === 'sending' ? 'Enviando…' : 'Borrador: llegará a ' . $count . ' apoderados') ?></h3></div>
    <div class="content">
        <button type="button" class="btn btn-sm btn-primary" data-request="onSend"
            data-request-confirm="¿Enviar por WhatsApp a <?= (int) $count ?> apoderados?"><?= $formModel->status === 'sent' ? 'Reintentar fallidos' : 'Enviar ahora' ?></button>
    </div>
</div>
<?php endif ?>
<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row"><?= $this->formRender() ?></div>
    <div class="form-buttons">
        <button type="submit" data-request="onSave" data-request-data="close:1" data-hotkey="ctrl+enter, cmd+enter" class="btn btn-primary">Guardar</button>
        <button type="button" class="btn btn-danger pull-right" data-request="onDelete" data-request-confirm="¿Eliminar?">Eliminar</button>
        <a href="<?= Backend::url('aero/school/announcements') ?>" class="btn btn-default">Cancelar</a>
    </div>
<?= Form::close() ?>
