<?php
$qs = http_build_query(array_filter(['report' => $f['report'], 'from' => $f['from'], 'to' => $f['to'], 'status' => $f['status'], 'branch' => $f['branch']]));
$filters = $allowed[$f['report']]['filters'] ?? [];
?>
<?php if (!$allowed): ?>
    <p class="text-muted">No tienes reportes disponibles.</p>
<?php else: ?>
<form method="get" class="form-inline" style="margin-bottom:15px">
    <select name="report" class="form-control custom-select" onchange="this.form.submit()">
        <?php foreach ($allowed as $k => $r): ?><option value="<?= e($k) ?>" <?= $f['report'] === $k ? 'selected' : '' ?>><?= e($r['label']) ?></option><?php endforeach ?>
    </select>
    <?php if (in_array('period', $filters, true)): ?>
        <label>Desde <input type="date" name="from" value="<?= e($f['from']) ?>" class="form-control"></label>
        <label>Hasta <input type="date" name="to" value="<?= e($f['to']) ?>" class="form-control"></label>
    <?php endif ?>
    <?php if (in_array('status', $filters, true)): ?>
        <select name="status" class="form-control custom-select"><option value="">Todos los estados</option>
            <?php foreach ($statuses as $k => $v): ?><option value="<?= e($k) ?>" <?= $f['status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach ?>
        </select>
    <?php endif ?>
    <?php if (in_array('branch', $filters, true)): ?>
        <select name="branch" class="form-control custom-select"><option value="">Todas las sedes</option>
            <?php foreach ($branches as $id => $name): ?><option value="<?= (int) $id ?>" <?= (int) $f['branch'] === (int) $id ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach ?>
        </select>
    <?php endif ?>
    <button class="btn btn-primary" type="submit">Ver</button>
    <a class="btn btn-default" href="<?= Backend::url('aero/church/reports/export/xlsx?' . $qs) ?>">Descargar Excel</a>
    <a class="btn btn-default" href="<?= Backend::url('aero/church/reports/export/pdf?' . $qs) ?>" target="_blank">Descargar PDF</a>
</form>

<?php if ($report): ?>
    <h3><?= e($report['title']) ?></h3>
    <table class="table data" style="max-width:640px;margin-bottom:20px">
        <?php foreach ($report['summary'] as $k => $v): ?><tr><td><strong><?= e($k) ?></strong></td><td><?= e($v) ?></td></tr><?php endforeach ?>
    </table>
    <div style="overflow:auto">
    <table class="table data">
        <thead><tr><?php foreach ($report['columns'] as $c): ?><th><?= e($c) ?></th><?php endforeach ?></tr></thead>
        <tbody>
        <?php foreach (array_slice($report['rows'], 0, 500) as $r): ?>
            <tr><?php foreach ($r as $v): ?><td><?= e($v) ?></td><?php endforeach ?></tr>
        <?php endforeach ?>
        <?php if (!$report['rows']): ?><tr><td colspan="<?= count($report['columns']) ?>" class="text-muted">Sin datos en este periodo.</td></tr><?php endif ?>
        </tbody>
    </table>
    </div>
    <?php if (count($report['rows']) > 500): ?><p class="text-muted">Se muestran 500 filas de <?= count($report['rows']) ?>; descarga el Excel para ver todas.</p><?php endif ?>
<?php endif ?>
<?php endif ?>
