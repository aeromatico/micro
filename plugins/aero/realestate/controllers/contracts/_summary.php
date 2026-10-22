<?php
$fmt = fn ($n) => number_format((float) $n, 2, ',', '.');
$left = $c->days_left;
?>
<div class="callout callout-<?= $c->status === 'active' ? ($left !== null && $left <= \Aero\Realestate\Models\Contract::EXPIRING_DAYS ? 'warning' : 'success') : 'info' ?>" style="margin-bottom:15px">
    <div class="header"><h3><?= e($c->code) ?> · <?= e($c->status_label) ?></h3></div>
    <div class="content">
        <p>
            Capital pactado <strong><?= $fmt($c->capital) ?> <?= e($c->currency) ?></strong> ·
            entregado <strong><?= $fmt($c->delivered_amount) ?></strong> ·
            devuelto <strong><?= $fmt($c->returned_amount) ?></strong> ·
            por devolver <strong><?= $fmt($c->pending_amount) ?></strong>
            (<?= e($c->return_status_name) ?>)
            <?php if ($c->end_date): ?>· vence el <strong><?= e($c->end_date->format('d/m/Y')) ?></strong><?php if ($left !== null): ?> (<?= $left >= 0 ? 'faltan ' . $left . ' días' : 'vencido hace ' . abs($left) . ' días' ?>)<?php endif ?><?php endif ?>
        </p>
        <?php if ($c->in_dispute): ?><p><strong>En disputa:</strong> <?= e($c->dispute_note) ?></p><?php endif ?>
        <p>
        <?php $id = (int) $c->id; ?>
        <?php if (in_array($c->status, ['draft'], true)): ?>
            <button type="button" class="btn btn-default" data-request="onReserve" data-request-data="id: <?= $id ?>" data-request-confirm="¿Reservar? La propiedad y la oferta quedan apartadas.">Reservar</button>
        <?php endif ?>
        <?php if (in_array($c->status, ['draft', 'reserved'], true)): ?>
            <button type="button" class="btn btn-primary" data-control="popup" data-handler="onActivateForm" data-request-data="id: <?= $id ?>">Activar contrato</button>
            <button type="button" class="btn btn-default" data-control="popup" data-handler="onCancelForm" data-request-data="id: <?= $id ?>">Cancelar contrato</button>
        <?php endif ?>
        <?php if ($c->status === 'active'): ?>
            <button type="button" class="btn btn-primary" data-control="popup" data-handler="onRenewForm" data-request-data="id: <?= $id ?>">Renovar</button>
            <button type="button" class="btn btn-default" data-request="onFinish" data-request-data="id: <?= $id ?>" data-request-confirm="¿Finalizar? El capital debe estar devuelto por completo.">Finalizar (capital devuelto)</button>
        <?php endif ?>
        <?php if (in_array($c->status, ['active', 'finished'], true)): ?>
            <?php if ($c->in_dispute): ?>
                <button type="button" class="btn btn-default" data-control="popup" data-handler="onResolveForm" data-request-data="id: <?= $id ?>">Resolver disputa</button>
            <?php else: ?>
                <button type="button" class="btn btn-default" data-control="popup" data-handler="onDisputeForm" data-request-data="id: <?= $id ?>">Marcar en disputa</button>
            <?php endif ?>
        <?php endif ?>
        </p>
    </div>
</div>
