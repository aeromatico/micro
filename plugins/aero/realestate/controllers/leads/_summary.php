<?php $id = (int) $m->id; $open = $m->is_open; ?>
<div class="callout callout-<?= $m->stage === 'closed_won' ? 'success' : ($m->stage === 'closed_lost' ? 'danger' : ($m->is_follow_up_due ? 'warning' : 'info')) ?>" style="margin-bottom:15px">
    <div class="header"><h3><?= e($m->client?->name) ?> · <?= e($m->stage_name) ?></h3></div>
    <div class="content">
        <p>
            <?php if ($m->property): ?>Propiedad: <strong><?= e($m->property->title) ?></strong> · <?php endif ?>
            <?php if ($m->modality): ?><?= e(\Aero\Realestate\Models\Offer::$modalities[$m->modality] ?? '') ?> · <?php endif ?>
            <?php if ($m->final_amount): ?>Cerrada por <strong><?= number_format((float) $m->final_amount, 2, ',', '.') ?></strong> · <?php endif ?>
            <?php if ($m->last_contact_at): ?>Último contacto <?= e($m->last_contact_at->diffForHumans()) ?> · <?php endif ?>
            <?php if ($m->next_follow_up_at && $open): ?>Seguimiento <strong><?= e($m->next_follow_up_at->format('d/m/Y H:i')) ?></strong><?= $m->is_follow_up_due ? ' (vencido)' : '' ?><?php endif ?>
            <?php if ($m->lost_reason): ?>Motivo: <?= e($m->lost_reason) ?><?php endif ?>
        </p>
        <?php if ($open): ?>
        <p>
            <button type="button" class="btn btn-primary" data-control="popup" data-handler="onContactForm" data-request-data="id: <?= $id ?>">Registrar contacto</button>
            <button type="button" class="btn btn-default" data-control="popup" data-handler="onStageForm" data-request-data="id: <?= $id ?>">Cambiar etapa</button>
            <button type="button" class="btn btn-success" data-control="popup" data-handler="onCloseForm" data-request-data="id: <?= $id ?>">Cerrar operación</button>
            <a class="btn btn-default" href="<?= Backend::url('aero/realestate/visits/create?lead_id=' . $id . '&client_id=' . (int) $m->client_id . '&property_id=' . (int) $m->property_id) ?>">Programar visita</a>
        </p>
        <?php endif ?>
    </div>
</div>
