<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/tickets/platformusers') ?>">Usuarios</a></li><li><?= e($this->pageTitle) ?></li></ul>
<?php Block::endPut() ?>
<?php if (!$this->fatalError): ?>
<?php $u = $formModel; $id = (int) $u->id; $can = \Aero\Tickets\Classes\Admin::can('organizers') && !$u->is_superuser && $u->id !== $this->user->id; ?>
<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row"><?= $this->formRender() ?></div>
    <div class="form-buttons">
    <?php if ($can): ?>
        <?php if ($u->isSuspendedNow()): ?>
            <button type="button" class="btn btn-success" onclick="var n=prompt('Nota (opcional)','');if(n!==null){$(this).request('onEnable',{data:{id:<?= $id ?>,note:n}})}">Habilitar cuenta</button>
        <?php else: ?>
            <button type="button" class="btn btn-warning" onclick="var r=prompt('Motivo de la suspensión (queda en el historial)');if(r){$(this).request('onSuspend',{data:{id:<?= $id ?>,reason:r}})}">Suspender cuenta</button>
        <?php endif ?>
        <?php if ($this->user->hasAccess('aero.tickets.superadmin') && $u->tenantsOwned()->isEmpty()): ?>
            <select id="role-sel" class="form-control" style="display:inline-block;width:auto">
                <option value="0">— Sin rol global —</option>
                <?php foreach ($u->getRoleIdOptions() as $rid => $rn): ?><option value="<?= (int) $rid ?>" <?= (int) $u->role_id === (int) $rid ? 'selected' : '' ?>><?= e($rn) ?></option><?php endforeach ?>
            </select>
            <button type="button" class="btn btn-default" onclick="$(this).request('onSetRole',{data:{id:<?= $id ?>,role_id:document.getElementById('role-sel').value}})">Asignar rol global</button>
        <?php endif ?>
    <?php endif ?>
        <a href="<?= Backend::url('aero/tickets/platformusers') ?>" class="btn btn-default">Volver</a>
    </div>
<?= Form::close() ?>
<?php else: ?>
    <p class="flash-message static error"><?= e($this->fatalError) ?></p>
<?php endif ?>
