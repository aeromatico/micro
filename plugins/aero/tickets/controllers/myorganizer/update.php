<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/tickets/events') ?>">Eventos</a></li><li>Mi organización</li></ul>
<?php Block::endPut() ?>
<?php if (!$this->fatalError): ?>
<?php $o = $formModel; $cls = ['approved' => 'success', 'pending' => 'warning', 'rejected' => 'danger', 'suspended' => 'danger'][$o->status] ?? 'info'; ?>
<div class="callout callout-<?= $cls ?>"><div class="content">
    <strong>Estado: <?= e($o->status_label) ?></strong>
    <?php if ($o->status === 'pending'): ?> — tu solicitud está en revisión; mientras tanto puedes preparar tus eventos en borrador.<?php endif ?>
    <?php if ($o->status === 'approved'): ?> — ya puedes publicar eventos y vender entradas.<?php endif ?>
    <?php if (in_array($o->status, ['rejected', 'suspended'], true)): ?> — <?= e($o->decision_note) ?>
        <?= $o->status === 'rejected' ? 'Corrige tus datos y guarda: la solicitud vuelve a revisión.' : 'Comunícate con soporte para reactivar tu cuenta.' ?><?php endif ?>
</div></div>
<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row"><?= $this->formRender() ?></div>
    <div class="form-buttons">
        <button type="submit" data-request="onSave" data-hotkey="ctrl+enter, cmd+enter" class="btn btn-primary">Guardar</button>
        <a href="<?= Backend::url('aero/tickets/events') ?>" class="btn btn-default">Volver</a>
    </div>
<?= Form::close() ?>
<?php else: ?>
    <p class="flash-message static error"><?= e($this->fatalError) ?></p>
<?php endif ?>
