<?php
$prev = $date->copy()->sub($view === 'week' ? '7 days' : '1 day')->toDateString();
$next = $date->copy()->add($view === 'week' ? '7 days' : '1 day')->toDateString();
$qs = fn (array $o) => '?' . http_build_query(array_filter(array_merge(['date' => $date->toDateString(), 'view' => $view, 'branch' => $branchId, 'worker' => $workerId], $o)));
$stColor = ['pending' => '#d97706', 'confirmed' => '#2563eb', 'in_service' => '#7c3aed', 'completed' => '#16a34a', 'no_show' => '#9a3412'];
$card = function ($b) use ($stColor) {
    $c = $stColor[$b->status] ?? '#6b7280';
    return '<a href="' . e(Backend::url('aero/office/bookings/update/' . $b->id)) . '" style="display:block;margin-bottom:6px;padding:6px 8px;border-left:4px solid ' . $c . ';background:#f9fafb;border-radius:4px;color:#111;text-decoration:none">'
        . '<strong>' . e($b->starts_at->format('H:i')) . '–' . e($b->ends_at->format('H:i')) . '</strong> '
        . '<span style="font-size:11px;color:' . $c . '">' . e($b->status_label) . '</span><br>'
        . e($b->customer?->name) . '<br><small class="text-muted">' . e($b->service_name) . ' · ' . e($b->worker_name) . '</small></a>';
};
?>
<div style="padding:15px">
    <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:16px">
        <a class="btn btn-default" href="<?= $qs(['date' => $prev]) ?>">‹</a>
        <input type="date" name="date" class="form-control" style="width:auto" value="<?= e($date->toDateString()) ?>" onchange="this.form.submit()">
        <a class="btn btn-default" href="<?= $qs(['date' => $next]) ?>">›</a>
        <a class="btn btn-default" href="<?= $qs(['date' => today()->toDateString()]) ?>">Hoy</a>
        <select name="view" class="form-control" style="width:auto" onchange="this.form.submit()">
            <option value="day" <?= $view === 'day' ? 'selected' : '' ?>>Día</option>
            <option value="week" <?= $view === 'week' ? 'selected' : '' ?>>Semana</option>
        </select>
        <select name="branch" class="form-control" style="width:auto" onchange="this.form.submit()">
            <option value="">Todas las sucursales</option>
            <?php foreach ($branches as $id => $n): ?><option value="<?= (int) $id ?>" <?= $id == $branchId ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach ?>
        </select>
        <select name="worker" class="form-control" style="width:auto" onchange="this.form.submit()">
            <option value="">Todos los profesionales</option>
            <?php foreach ($workers as $id => $n): ?><option value="<?= (int) $id ?>" <?= $id == $workerId ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach ?>
        </select>
        <a class="btn btn-primary oc-icon-plus" href="<?= Backend::url('aero/office/bookings/create') ?>">Nueva reserva</a>
    </form>

    <h4><?= e($view === 'week' ? 'Semana del ' . $from->translatedFormat('d M') . ' al ' . $from->copy()->endOfWeek()->translatedFormat('d M Y') : ucfirst($date->translatedFormat('l d \d\e F Y'))) ?></h4>

<?php if ($view === 'day'): ?>
    <?php $by = $bookings->groupBy(fn ($b) => $b->worker_name ?: '—'); ?>
    <?php if ($by->isEmpty()): ?>
        <p class="text-muted">Sin citas este día.</p>
    <?php else: ?>
    <div style="display:flex;gap:14px;overflow-x:auto;align-items:flex-start">
        <?php foreach ($by as $name => $list): ?>
        <div style="min-width:240px;flex:1">
            <div style="font-weight:600;border-bottom:2px solid #e5e7eb;padding-bottom:4px;margin-bottom:8px"><?= e($name) ?> <small class="text-muted">(<?= $list->count() ?>)</small></div>
            <?php foreach ($list as $b) { echo $card($b); } ?>
        </div>
        <?php endforeach ?>
    </div>
    <?php endif ?>
<?php else: ?>
    <div style="display:flex;gap:10px;overflow-x:auto;align-items:flex-start">
        <?php for ($i = 0; $i < 7; $i++): $d = $from->copy()->addDays($i); $list = $bookings->filter(fn ($b) => $b->starts_at->isSameDay($d)); ?>
        <div style="min-width:170px;flex:1">
            <div style="font-weight:600;border-bottom:2px solid <?= $d->isToday() ? '#2563eb' : '#e5e7eb' ?>;padding-bottom:4px;margin-bottom:8px">
                <a href="<?= $qs(['date' => $d->toDateString(), 'view' => 'day']) ?>"><?= e(ucfirst($d->translatedFormat('D d'))) ?></a>
                <small class="text-muted">(<?= $list->count() ?>)</small>
            </div>
            <?php foreach ($list as $b) { echo $card($b); } ?>
        </div>
        <?php endfor ?>
    </div>
<?php endif ?>
</div>
