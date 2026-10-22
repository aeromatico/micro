<?php
$url = fn ($l) => Backend::url('aero/realestate/leads/update/' . $l->id);
?>
<div style="display:flex;gap:12px;overflow-x:auto;padding-bottom:10px;align-items:flex-start">
<?php foreach ($board as $key => $col): ?>
    <div data-stage="<?= e($key) ?>" aria-label="<?= e($col['label']) ?>" style="min-width:240px;flex:1;background:var(--bs-tertiary-bg, rgba(127,127,127,.12));border:1px solid var(--bs-border-color);border-radius:6px;padding:10px;color:var(--bs-body-color)">
        <h5 style="margin:0 0 8px"><?= e($col['label']) ?> <span class="badge"><?= (int) $col['total'] ?></span></h5>
        <div data-cards style="min-height:48px">
        <?php foreach ($col['items'] as $l): ?>
            <div data-lead="<?= (int) $l->id ?>" data-stage-of="<?= e($key) ?>" draggable="<?= $key === 'closed_won' ? 'false' : 'true' ?>"
                 style="background:var(--bs-body-bg);border:1px solid var(--bs-border-color);border-radius:4px;padding:8px;margin-bottom:6px;color:var(--bs-body-color);cursor:<?= $key === 'closed_won' ? 'default' : 'grab' ?>;border-left:3px solid <?= $l->is_follow_up_due ? 'var(--bs-warning)' : 'var(--bs-border-color)' ?>">
                <a href="<?= $url($l) ?>" draggable="false" style="color:inherit;text-decoration:none;display:block">
                    <strong><?= e($l->client?->name) ?></strong><br>
                    <small style="color:var(--bs-secondary-color)"><?= e($l->property?->title ?? 'Sin propiedad') ?></small><br>
                    <small><?= e($l->agent?->name ?? 'Sin agente') ?><?= $l->next_follow_up_at && $l->is_open ? ' · ' . e($l->next_follow_up_at->format('d/m H:i')) : '' ?></small>
                </a>
            </div>
        <?php endforeach ?>
        </div>
        <?php if ($col['total'] > $col['items']->count()): ?><small style="color:var(--bs-secondary-color)">y <?= $col['total'] - $col['items']->count() ?> más…</small><?php endif ?>
    </div>
<?php endforeach ?>
</div>
