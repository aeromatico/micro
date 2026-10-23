<?php
$stats = app(\Aero\Church\Classes\CareService::class)->visitStats(now()->startOfYear()->toDateString(), now()->toDateString(), \Aero\Church\Classes\CurrentTenant::isAdmin() ? null : \Aero\Church\Classes\CurrentTenant::id());
$labels = \Aero\Church\Models\Visit::STATUSES;
?>
<div data-control="toolbar">
    <a href="<?= Backend::url('aero/church/visits/create') ?>" class="btn btn-primary oc-icon-plus">Nuevo</a>
    <span class="text-muted" style="margin-left:15px">
        Este año: <strong><?= (int) $stats['total'] ?></strong> visitantes ·
        <?php foreach ($stats['by_status'] as $st => $n): ?><?= e($labels[$st] ?? $st) ?>: <?= (int) $n ?> · <?php endforeach ?>
        integrados: <strong><?= $stats['rate'] ?>%</strong>
    </span>
</div>
