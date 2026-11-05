<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/tickets/buyers') ?>">Compradores</a></li><li><?= e($this->pageTitle) ?></li></ul>
<?php Block::endPut() ?>
<?php if (!$this->fatalError): ?>
<?php $b = $formModel; $id = (int) $b->id; ?>
<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row"><?= $this->formRender() ?></div>
    <div class="form-buttons">
    <?php if (\Aero\Tickets\Classes\Admin::can('organizers')): ?>
        <?php if ($b->status === 'active'): ?>
            <button type="button" class="btn btn-danger" onclick="var r=prompt('Motivo del bloqueo (queda en el historial)');if(r){$(this).request('onBlock',{data:{id:<?= $id ?>,reason:r}})}">Bloquear compras</button>
        <?php else: ?>
            <button type="button" class="btn btn-success" onclick="var n=prompt('Nota (opcional)','');if(n!==null){$(this).request('onUnblock',{data:{id:<?= $id ?>,note:n}})}">Desbloquear</button>
        <?php endif ?>
    <?php endif ?>
        <a href="<?= Backend::url('aero/tickets/buyers') ?>" class="btn btn-default">Volver</a>
    </div>
<?= Form::close() ?>
<?php else: ?>
    <p class="flash-message static error"><?= e($this->fatalError) ?></p>
<?php endif ?>
