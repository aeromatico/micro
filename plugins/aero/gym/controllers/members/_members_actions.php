<?php $m = $this->formGetModel(); if (!$m || !$m->exists) return; ?>
<div class="callout callout-info" style="margin-bottom:10px">
    <div class="header"><h3>Credencial: <code><?= e($m->qr_token) ?></code></h3></div>
    <div class="content">
        <a href="<?= Backend::url('aero/gym/members/card/' . $m->id) ?>" class="btn btn-default oc-icon-qrcode" target="_blank">Ver / imprimir carnet con QR</a>
        <a href="<?= Backend::url('aero/gym/memberships/create') ?>" class="btn btn-default oc-icon-id-card">Nueva membresía</a>
        <?php if ($m->user_id): ?>
            <span class="label label-success" style="margin-left:8px">Usuario #<?= (int) $m->user_id ?> vinculado</span>
        <?php else: ?>
            <button type="button" class="btn btn-default oc-icon-user" data-request="onCreateUser" data-request-confirm="¿Crear el usuario de acceso para este socio?">Crear usuario</button>
        <?php endif ?>
        <?php $ms = $m->currentMembership(); ?>
        <span style="margin-left:8px"><?= $ms ? 'Membresía vigente hasta ' . $ms->ends_on->format('d/m/Y') : '<b style="color:#c0392b">Sin membresía vigente</b>' ?></span>
    </div>
</div>
