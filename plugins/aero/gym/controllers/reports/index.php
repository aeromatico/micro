<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/gym/reports') ?>">Gimnasio</a></li><li>Reportes</li></ul>
<?php Block::endPut() ?>
<form method="get" class="form-inline" style="margin-bottom:16px">
    Del <input type="date" name="from" value="<?= $from->toDateString() ?>" class="form-control" style="width:auto">
    al <input type="date" name="to" value="<?= $to->toDateString() ?>" class="form-control" style="width:auto">
    <button class="btn btn-primary">Ver</button>
</form>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin-bottom:20px">
    <?php foreach ([['Socios', $summary['members']], ['Con membresía vigente', $summary['active']], ['Vencen en 7 días', $summary['expiring_7']], ['Pendientes de pago', $summary['pending_payment']], ['Ocupación de clases', $occupancy . ' %']] as [$l, $v]): ?>
        <div style="background:color-mix(in srgb, var(--bs-body-color) 5%, var(--bs-body-bg));border:1px solid var(--bs-border-color);border-radius:6px;padding:12px"><div style="color:var(--bs-secondary-color);font-size:12px"><?= e($l) ?></div><div style="font-size:26px;font-weight:700"><?= e($v) ?></div></div>
    <?php endforeach ?>
</div>

<h4>Asistencia (ingresos concedidos por día)</h4>
<?php $max = max(1, max($days ?: [0])); ?>
<div style="display:flex;align-items:flex-end;gap:2px;height:120px;background:color-mix(in srgb, var(--bs-body-color) 5%, var(--bs-body-bg));border:1px solid var(--bs-border-color);border-radius:6px;padding:8px;margin-bottom:6px;overflow-x:auto">
    <?php foreach ($days as $d => $n): ?>
        <div title="<?= e($d) ?>: <?= $n ?>" style="flex:1;min-width:6px;background:var(--oc-accent, #3498db);height:<?= max(1, round($n / $max * 100)) ?>%;opacity:<?= $n ? 1 : .15 ?>"></div>
    <?php endforeach ?>
</div>
<p class="text-muted">Total: <?= array_sum($days) ?> ingresos · promedio <?= count($days) ? round(array_sum($days) / count($days), 1) : 0 ?> por día</p>

<h4>Clases más demandadas</h4>
<table class="table data">
    <thead><tr><th>Clase</th><th>Sesiones</th><th>Reservas</th><th>Asistencias</th><th>Promedio por sesión</th></tr></thead>
    <tbody>
    <?php foreach ($classes as $c): ?>
        <tr><td><?= e($c['name']) ?></td><td><?= $c['sessions'] ?></td><td><?= $c['bookings'] ?></td><td><?= $c['attended'] ?></td><td><?= $c['avg_per_session'] ?></td></tr>
    <?php endforeach ?>
    <?php if (!$classes): ?><tr><td colspan="5" class="text-muted">Sin clases en el rango.</td></tr><?php endif ?>
    </tbody>
</table>

<h4>Renovaciones vs. bajas</h4>
<table class="table data">
    <thead><tr><th>Mes</th><th>Nuevos</th><th>Renovaciones</th><th>Bajas</th><th>Balance</th></tr></thead>
    <tbody>
    <?php foreach ($churn as $month => $row): ?>
        <tr><td><?= e($month) ?></td><td><?= $row['new'] ?></td><td><?= $row['renewed'] ?></td><td><?= $row['churned'] ?></td>
            <td style="font-weight:600;color:<?= ($row['new'] + $row['renewed'] - $row['churned']) >= 0 ? '#2e9e5b' : '#c0392b' ?>"><?= $row['new'] + $row['renewed'] - $row['churned'] ?></td></tr>
    <?php endforeach ?>
    </tbody>
</table>
