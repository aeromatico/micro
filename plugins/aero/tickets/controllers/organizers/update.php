<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/tickets/organizers') ?>">Organizadores</a></li><li><?= e($this->pageTitle) ?></li></ul>
<?php Block::endPut() ?>
<?php if (!$this->fatalError): ?>
<?php $o = $formModel; $id = (int) $o->id; $can = \Aero\Tickets\Classes\Admin::can('organizers'); ?>
<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row"><?= $this->formRender() ?></div>
    <div class="form-buttons">
    <?php if ($can): ?>
        <?php if (in_array($o->status, ['pending', 'rejected'], true)): ?>
            <button type="button" class="btn btn-success" onclick="var n=prompt('Nota para el organizador (opcional)','');if(n!==null){$(this).request('onApprove',{data:{id:<?= $id ?>,note:n}})}">Aprobar</button>
        <?php endif ?>
        <?php if ($o->status === 'pending'): ?>
            <button type="button" class="btn btn-danger" onclick="var n=prompt('Motivo del rechazo (se le informa al organizador)');if(n){$(this).request('onReject',{data:{id:<?= $id ?>,note:n}})}">Rechazar</button>
        <?php endif ?>
        <?php if (in_array($o->status, ['approved', 'pending'], true)): ?>
            <button type="button" class="btn btn-warning" onclick="var n=prompt('Motivo de la suspensión (se le informa al organizador). Sus eventos dejan de vender, mostrarse e ingresar.');if(n){$(this).request('onSuspend',{data:{id:<?= $id ?>,note:n}})}">Suspender</button>
        <?php endif ?>
        <?php if ($o->status === 'suspended'): ?>
            <button type="button" class="btn btn-success" onclick="var n=prompt('Nota (opcional)','');if(n!==null){$(this).request('onReinstate',{data:{id:<?= $id ?>,note:n}})}">Reactivar</button>
        <?php endif ?>
    <?php endif ?>
        <a href="<?= Backend::url('aero/tickets/organizers') ?>" class="btn btn-default">Volver</a>
    </div>
<?= Form::close() ?>
<?php else: ?>
    <p class="flash-message static error"><?= e($this->fatalError) ?></p>
<?php endif ?>
