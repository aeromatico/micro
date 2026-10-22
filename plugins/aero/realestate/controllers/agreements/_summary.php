<?php
$id = (int) $m->id; $left = $m->days_left; $admin = \Aero\Realestate\Classes\CurrentTenant::isAdmin() || (\BackendAuth::getUser()?->hasAccess('aero.realestate.use'));
$mark = \Aero\Realestate\Classes\AgreementPdf::watermark($m);
?>
<div class="callout callout-<?= $m->status === 'signed' ? 'success' : ($m->status === 'cancelled' ? 'danger' : ($mark ? 'warning' : 'info')) ?>" style="margin-bottom:15px">
    <div class="header"><h3><?= e($m->code) ?> · <?= e($m->type_name) ?> · <?= e($m->status_name) ?></h3></div>
    <div class="content">
        <?php if (!$m->template_reviewed): ?><p><strong>⚠ Plantilla sin revisión legal.</strong> Este texto es un borrador orientativo: no se puede aprobar ni firmar en el sistema hasta que un abogado revise la plantilla y se regenere el contrato.</p><?php endif ?>
        <p>
            <?= e($m->property?->title) ?> — <?= e($m->client?->name ?? 'sin cliente') ?> / <?= e($m->owner?->name ?? 'sin propietario') ?> ·
            <?= number_format((float) $m->amount, 2, ',', '.') ?> <?= e($m->currency) ?>
            <?php if ($m->end_date): ?>· vence <strong><?= e($m->end_date->format('d/m/Y')) ?></strong><?php if ($left !== null && $m->status === 'signed'): ?> (<?= $left >= 0 ? 'faltan ' . $left . ' días' : 'vencido hace ' . abs($left) . ' días' ?>)<?php endif ?><?php endif ?>
            <?php if ($m->signed_on): ?>· firmado el <?= e($m->signed_on->format('d/m/Y')) ?><?php endif ?>
        </p>
        <p>
            <a class="btn btn-default" target="_blank" href="<?= Backend::url('aero/realestate/agreements/preview/' . $id) ?>">Vista previa</a>
            <a class="btn btn-default" href="<?= Backend::url('aero/realestate/agreements/pdf/' . $id) ?>">Descargar PDF</a>
            <?php if ($m->is_editable): ?>
                <button type="button" class="btn btn-default" data-request="onRegenerate" data-request-data="id: <?= $id ?>" data-request-confirm="Se vuelve a rellenar el texto desde la plantilla y se pierden las ediciones manuales. ¿Continuar?">Regenerar texto</button>
            <?php endif ?>
            <?php if ($m->status === 'draft'): ?><button type="button" class="btn btn-default" data-request="onSubmit" data-request-data="id: <?= $id ?>">Enviar a revisión</button><?php endif ?>
            <?php if ($admin && in_array($m->status, ['draft', 'in_review'], true)): ?><button type="button" class="btn btn-primary" data-request="onApprove" data-request-data="id: <?= $id ?>" data-request-confirm="¿Aprobar el contrato? Quedará bloqueado para edición.">Aprobar</button><?php endif ?>
            <?php if ($admin && $m->status === 'approved'): ?>
                <button type="button" class="btn btn-success" data-control="popup" data-handler="onSignForm" data-request-data="id: <?= $id ?>">Registrar firma</button>
                <button type="button" class="btn btn-default" data-control="popup" data-handler="onReopenForm" data-request-data="id: <?= $id ?>">Reabrir para corregir</button>
            <?php endif ?>
            <?php if ($admin && $m->status === 'signed' && $m->type !== 'arras'): ?><button type="button" class="btn btn-primary" data-control="popup" data-handler="onRenewForm" data-request-data="id: <?= $id ?>">Renovar</button><?php endif ?>
            <?php if ($admin && $m->status === 'signed'): ?><button type="button" class="btn btn-default" data-request="onFinish" data-request-data="id: <?= $id ?>" data-request-confirm="¿Dar por finalizado el contrato?">Finalizar</button><?php endif ?>
            <?php if (in_array($m->status, ['draft', 'in_review', 'approved'], true)): ?><button type="button" class="btn btn-default" data-control="popup" data-handler="onCancelForm" data-request-data="id: <?= $id ?>">Cancelar contrato</button><?php endif ?>
        </p>
    </div>
</div>
