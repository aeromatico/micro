<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/tickets/adminevents') ?>">Eventos de la plataforma</a></li><li><?= e($this->pageTitle) ?></li></ul>
<?php Block::endPut() ?>
<?php if (!$this->fatalError): ?>
<?php $e = $formModel; $id = (int) $e->id; $can = \Aero\Tickets\Classes\Admin::can('events'); ?>
<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row"><?= $this->formRender() ?></div>
    <div class="form-buttons">
    <?php if ($can): ?>
        <?php if (in_array($e->moderation, ['pending', 'rejected'], true)): ?>
            <button type="button" class="btn btn-success" onclick="var n=prompt('Nota (opcional)','');if(n!==null){$(this).request('onApprove',{data:{id:<?= $id ?>,note:n}})}">Aprobar publicación</button>
        <?php endif ?>
        <?php if ($e->moderation === 'pending'): ?>
            <button type="button" class="btn btn-danger" onclick="var n=prompt('Qué debe corregir el organizador (se le envía por correo)');if(n){$(this).request('onReject',{data:{id:<?= $id ?>,note:n}})}">Rechazar</button>
        <?php endif ?>
        <?php if (in_array($e->moderation, ['ok', 'pending'], true) && in_array($e->status, ['published', 'ongoing'], true)): ?>
            <button type="button" class="btn btn-warning" onclick="var n=prompt('Motivo de la suspensión (se le informa al organizador)');if(n){$(this).request('onSuspend',{data:{id:<?= $id ?>,note:n}})}">Suspender</button>
        <?php endif ?>
        <?php if ($e->moderation === 'suspended'): ?>
            <button type="button" class="btn btn-success" onclick="var n=prompt('Nota (opcional)','');if(n!==null){$(this).request('onRestore',{data:{id:<?= $id ?>,note:n}})}">Restablecer</button>
        <?php endif ?>
        <?php if (!in_array($e->status, ['cancelled', 'finished'], true)): ?>
            <button type="button" class="btn btn-danger" onclick="if(confirm('¿Cancelar el evento? Se anulan sus órdenes y los pagos recibidos quedan para devolución.')){var n=prompt('Motivo de la cancelación (se le informa al organizador)');if(n){$(this).request('onCancelEvent',{data:{id:<?= $id ?>,note:n}})}}">Cancelar evento</button>
        <?php endif ?>
    <?php endif ?>
        <a href="<?= Backend::url('aero/tickets/adminevents') ?>" class="btn btn-default">Volver</a>
    </div>
<?= Form::close() ?>
<?php else: ?>
    <p class="flash-message static error"><?= e($this->fatalError) ?></p>
<?php endif ?>
