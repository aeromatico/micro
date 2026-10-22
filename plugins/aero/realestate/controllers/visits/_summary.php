<?php $id = (int) $m->id; $active = in_array($m->status, \Aero\Realestate\Models\Visit::ACTIVE, true); ?>
<div class="callout callout-<?= $m->status === 'done' ? 'success' : ($m->status === 'cancelled' ? 'danger' : 'info') ?>" style="margin-bottom:15px">
    <div class="header"><h3><?= e($m->code) ?> · <?= e($m->status_name) ?> · <?= e($m->starts_at?->format('d/m/Y H:i')) ?></h3></div>
    <div class="content">
        <p><?= e($m->property?->title) ?> — <?= e($m->client?->name) ?><?= $m->agent ? ' · Agente: ' . e($m->agent->name) : '' ?><?= $m->cancel_reason ? ' · Motivo: ' . e($m->cancel_reason) : '' ?></p>
        <?php if ($active): ?>
        <p>
            <?php if ($m->status === 'requested'): ?>
                <button type="button" class="btn btn-primary" data-request="onConfirm" data-request-data="id: <?= $id ?>" data-request-confirm="¿Confirmar la visita y avisar al cliente y al agente?">Confirmar</button>
            <?php else: ?>
                <button type="button" class="btn btn-success" data-control="popup" data-handler="onDoneForm" data-request-data="id: <?= $id ?>">Marcar realizada</button>
            <?php endif ?>
            <button type="button" class="btn btn-default" data-control="popup" data-handler="onRescheduleForm" data-request-data="id: <?= $id ?>">Reprogramar</button>
            <button type="button" class="btn btn-default" data-control="popup" data-handler="onCancelForm" data-request-data="id: <?= $id ?>">Cancelar visita</button>
        </p>
        <?php endif ?>
    </div>
</div>
