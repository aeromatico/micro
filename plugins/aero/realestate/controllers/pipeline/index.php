<?php $url = fn ($l) => Backend::url('aero/realestate/leads/update/' . $l->id); ?>
<div style="display:flex;gap:12px;overflow-x:auto;padding-bottom:10px;align-items:flex-start">
<?php foreach ($board as $key => $col): ?>
    <div style="min-width:240px;flex:1;background:var(--bs-tertiary-bg, rgba(127,127,127,.12));border:1px solid var(--bs-border-color);border-radius:6px;padding:10px;color:var(--bs-body-color)">
        <h5 style="margin:0 0 8px"><?= e($col['label']) ?> <span class="badge"><?= (int) $col['total'] ?></span></h5>
        <?php foreach ($col['items'] as $l): ?>
            <a href="<?= $url($l) ?>" style="display:block;background:var(--bs-body-bg);border:1px solid var(--bs-border-color);border-radius:4px;padding:8px;margin-bottom:6px;color:var(--bs-body-color);text-decoration:none;border-left:3px solid <?= $l->is_follow_up_due ? 'var(--bs-warning)' : 'var(--bs-border-color)' ?>">
                <strong><?= e($l->client?->name) ?></strong><br>
                <small style="color:var(--bs-secondary-color)"><?= e($l->property?->title ?? 'Sin propiedad') ?></small><br>
                <small><?= e($l->agent?->name ?? 'Sin agente') ?><?= $l->next_follow_up_at && $l->is_open ? ' · ' . e($l->next_follow_up_at->format('d/m H:i')) : '' ?></small>
            </a>
        <?php endforeach ?>
        <?php if ($col['total'] > $col['items']->count()): ?><small style="color:var(--bs-secondary-color)">y <?= $col['total'] - $col['items']->count() ?> más…</small><?php endif ?>
    </div>
<?php endforeach ?>
</div>
<h4 style="margin-top:25px">Próximas visitas</h4>
<?php if ($visits->isEmpty()): ?><p class="text-muted">Sin visitas programadas.</p><?php else: ?>
<table class="table data"><thead><tr><th>Fecha</th><th>Propiedad</th><th>Cliente</th><th>Agente</th><th>Estado</th></tr></thead><tbody>
<?php foreach ($visits as $v): ?>
    <tr><td><a href="<?= Backend::url('aero/realestate/visits/update/' . $v->id) ?>"><?= e($v->starts_at->format('d/m/Y H:i')) ?></a></td><td><?= e($v->property?->title) ?></td><td><?= e($v->client?->name) ?></td><td><?= e($v->agent?->name) ?></td><td><?= e($v->status_name) ?></td></tr>
<?php endforeach ?>
</tbody></table>
<?php endif ?>
