<?php
use Aero\Realestate\Classes\Charts;
$money = fn ($n) => number_format((float) $n, 2, ',', '.');
$qs = fn (array $extra = []) => http_build_query(array_filter(['days' => $stats->days, 'tenant' => $stats->tenantId] + $extra));
?>
<form method="get" class="form-inline" style="margin-bottom:18px;display:flex;gap:10px;flex-wrap:wrap;align-items:center">
    <label for="days" style="margin:0">Periodo</label>
    <select id="days" name="days" class="form-control" onchange="this.form.submit()" style="width:auto">
        <?php foreach (\Aero\Realestate\Classes\Stats::PERIODS as $k => $label): ?><option value="<?= $k ?>" <?= $stats->days === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach ?>
    </select>
    <?php if ($isAdmin): ?>
        <label for="tenant" style="margin:0">Inmobiliaria</label>
        <select id="tenant" name="tenant" class="form-control" onchange="this.form.submit()" style="width:auto"><option value="">Todas</option>
            <?php foreach ($tenants as $id => $name): ?><option value="<?= $id ?>" <?= (string) $stats->tenantId === (string) $id ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach ?>
        </select>
    <?php endif ?>
    <span class="text-muted" style="font-size:12px">Desde el <?= e($stats->from->format('d/m/Y')) ?> · conversión global <?= $conversion['rate'] === null ? '—' : e(number_format($conversion['rate'], 1, ',', '')) . ' %' ?></span>
</form>

<h4>Productividad por agente</h4>
<p><a class="btn btn-default btn-sm" href="<?= Backend::url('aero/realestate/reports/export/agents?' . $qs()) ?>">Exportar CSV</a></p>
<?php if (!$agents): ?><p class="text-muted">Sin agentes ni actividad en el periodo.</p><?php else: ?>
<div class="table-responsive"><table class="table data">
    <thead><tr><th>Agente</th><th>Oportunidades</th><th>Contactadas</th><th>Visitas realizadas</th><th>Cierres</th><th>Venta / alquiler / anticrético</th><th>Conversión</th><th>Días a cierre</th><th>Comisiones</th></tr></thead>
    <tbody>
    <?php foreach ($agents as $a): ?>
        <tr<?= $a['id'] === null ? ' class="text-muted"' : '' ?>>
            <td><strong><?= e($a['name']) ?></strong><?= $a['active'] ? '' : ' <small>(inactivo)</small>' ?></td>
            <td><?= (int) $a['leads'] ?></td><td><?= (int) $a['contacted'] ?></td><td><?= (int) $a['visits'] ?></td><td><strong><?= (int) $a['won'] ?></strong></td>
            <td><?= (int) $a['by_modality']['sale'] ?> / <?= (int) $a['by_modality']['rent'] ?> / <?= (int) $a['by_modality']['anticretico'] ?></td>
            <td><?= $a['rate'] === null ? '—' : e(number_format($a['rate'], 1, ',', '')) . ' %' ?></td><td><?= $a['avg_days'] === null ? '—' : (int) $a['avg_days'] ?></td>
            <td><?php if (!$a['money']): ?>—<?php endif ?><?php foreach ($a['money'] as $cur => $x): ?><div><?= e($cur) ?> <?= $money($x['agency'] ?? 0) ?> <small class="text-muted">(agente <?= $money($x['agent'] ?? 0) ?>)</small></div><?php endforeach ?></td>
        </tr>
    <?php endforeach ?></tbody></table></div>
<p class="text-muted" style="font-size:12px">Conversión = cierres sobre las oportunidades del agente creadas en el periodo. «Días a cierre» promedia desde que entró la oportunidad hasta cerrarse.</p>
<?php endif ?>

<h4 style="margin-top:28px">Comisiones del periodo</h4>
<?php if (!$commissions): ?><p class="text-muted">Sin comisiones.</p><?php else: ?>
<table class="table data" style="max-width:640px"><thead><tr><th>Moneda</th><th>Comisión de la inmobiliaria</th><th>Parte de agentes</th><th>Pagado</th><th>Pendiente</th><th>Operaciones</th></tr></thead><tbody>
<?php foreach ($commissions as $cur => $x): ?><tr><td><?= e($cur) ?></td><td><?= $money($x['total']) ?></td><td><?= $money($x['agents']) ?></td><td><?= $money($x['paid']) ?></td><td><?= $money($x['pending']) ?></td><td><?= (int) $x['n'] ?></td></tr><?php endforeach ?>
</tbody></table><?php endif ?>

<h4 style="margin-top:28px">Demanda</h4>
<p><a class="btn btn-default btn-sm" href="<?= Backend::url('aero/realestate/reports/export/demand?' . $qs()) ?>">Exportar CSV</a></p>
<div class="row">
    <div class="col-md-4"><strong>Por ciudad</strong><?= Charts::hbars($demand['city'], '#1abc9c') ?></div>
    <div class="col-md-4"><strong>Por zona</strong><?= Charts::hbars($demand['zone'], '#3498db') ?></div>
    <div class="col-md-4"><strong>Por tipo</strong><?= Charts::hbars($demand['type'], '#e67e22') ?></div>
</div>
