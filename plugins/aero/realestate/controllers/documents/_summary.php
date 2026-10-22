<?php $id = (int) $m->id; $admin = \Aero\Realestate\Classes\CurrentTenant::isAdmin() || \BackendAuth::getUser()?->hasAccess('aero.realestate.use'); ?>
<div class="callout callout-<?= $m->status === 'verified' ? 'success' : ($m->status === 'rejected' ? 'danger' : 'warning') ?>" style="margin-bottom:15px">
    <div class="header"><h3><?= e($m->name) ?> · <?= e($m->status_name) ?></h3></div>
    <div class="content"><p><?= e($m->subject) ?><?= $m->verified_at ? ' · verificado el ' . e($m->verified_at->format('d/m/Y')) : '' ?></p>
    <p>
        <?php if ($m->file): ?><a class="btn btn-default" href="<?= Backend::url('aero/realestate/documents/download/' . $id) ?>">Descargar archivo</a><?php else: ?><span class="text-muted">Sin archivo adjunto.</span><?php endif ?>
        <?php if ($admin && $m->status !== 'verified'): ?><button type="button" class="btn btn-success" data-request="onVerify" data-request-data="id: <?= $id ?>">Marcar verificado</button><?php endif ?>
        <?php if ($admin && $m->status !== 'rejected'): ?><button type="button" class="btn btn-default" data-request="onReject" data-request-data="id: <?= $id ?>" data-request-confirm="¿Marcar como observado? Anota el motivo en las observaciones.">Observar</button><?php endif ?>
    </p></div>
</div>
