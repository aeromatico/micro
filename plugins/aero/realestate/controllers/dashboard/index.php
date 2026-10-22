<?php
use Aero\Realestate\Classes\Charts;
$money = fn ($n) => number_format((float) $n, 0, ',', '.');
$m = $d['modality']; $s = $d['status']; $c = $d['conversion']; $v = $d['visits']; $mk = $d['market']; $a = $d['attention'];
$link = fn ($path) => Backend::url('aero/realestate/' . $path);
$card = function (string $label, $value, string $hint = '', string $tone = '') { ?>
    <div style="flex:1 1 170px;min-width:160px;padding:14px 16px;border-radius:6px;border:1px solid var(--bs-border-color);background:var(--bs-body-bg);color:var(--bs-body-color);<?= $tone === 'warn' ? 'border-left:4px solid var(--bs-warning)' : ($tone === 'bad' ? 'border-left:4px solid var(--bs-danger)' : '') ?>">
        <div style="font-size:12px;opacity:.7"><?= e($label) ?></div>
        <div style="font-size:26px;font-weight:700;line-height:1.2"><?= $value ?></div>
        <?php if ($hint): ?><div style="font-size:11px;opacity:.65"><?= $hint ?></div><?php endif ?>
    </div>
<?php };
$box = fn (string $title) => '<h4 style="margin:26px 0 10px">' . e($title) . '</h4>';
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
    <span class="text-muted" style="font-size:12px">Desde el <?= e($stats->from->format('d/m/Y')) ?></span>
</form>

<?php if ($a['visits_requested'] || $a['follow_ups'] || $a['documents_pending'] || $a['agreements_review'] || $a['unassigned_leads'] || ($canMoney && $a['commissions_pending'])): ?>
<div class="callout callout-warning" style="margin-bottom:6px"><div class="header"><h3>Requiere tu atención</h3></div><div class="content"><ul style="margin:0 0 0 18px">
    <?php if ($a['visits_requested']): ?><li><a href="<?= $link('visits') ?>"><?= (int) $a['visits_requested'] ?> visita(s) solicitada(s)</a> por confirmar</li><?php endif ?>
    <?php if ($a['follow_ups']): ?><li><a href="<?= $link('leads') ?>"><?= (int) $a['follow_ups'] ?> seguimiento(s) vencido(s)</a></li><?php endif ?>
    <?php if ($a['unassigned_leads']): ?><li><a href="<?= $link('leads') ?>"><?= (int) $a['unassigned_leads'] ?> oportunidad(es) sin agente</a></li><?php endif ?>
    <?php if ($a['documents_pending']): ?><li><a href="<?= $link('documents') ?>"><?= (int) $a['documents_pending'] ?> documento(s) por verificar</a></li><?php endif ?>
    <?php if ($a['agreements_review']): ?><li><a href="<?= $link('agreements') ?>"><?= (int) $a['agreements_review'] ?> contrato(s) en revisión</a></li><?php endif ?>
    <?php if ($canMoney && $a['commissions_pending']): ?><li><a href="<?= $link('commissions') ?>"><?= (int) $a['commissions_pending'] ?> comisión(es) pendiente(s)</a></li><?php endif ?>
</ul></div></div>
<?php endif ?>

<?= $box('Catálogo') ?>
<div style="display:flex;gap:12px;flex-wrap:wrap">
    <?php $card('Publicadas en venta', (int) $m['sale']); $card('Publicadas en alquiler', (int) $m['rent']); $card('Publicadas en anticrético', (int) $m['anticretico']);
    $card('Disponibles', (int) $s['available'], 'de ' . (int) $s['total'] . ' propiedades'); $card('Reservadas', (int) $s['reserved']);
    $card('Operaciones cerradas', (int) $s['closed'], (int) $s['sold'] . ' vendidas · ' . (int) $s['rented'] . ' alquiladas · ' . (int) $s['anticretico'] . ' en anticrético'); ?>
</div>
<p style="margin-top:10px;font-size:12px" class="text-muted">Una propiedad con varias ofertas cuenta en cada modalidad. Sin publicar: <?= (int) $s['unpublished'] ?>. Inactivas: <?= (int) $s['inactive'] ?>.</p>
<?= Charts::stacked(['Disponibles' => [$s['available'], '#2ecc71'], 'Reservadas' => [$s['reserved'], '#f1c40f'], 'Vendidas' => [$s['sold'], '#3498db'], 'Alquiladas' => [$s['rented'], '#9b59b6'], 'En anticrético' => [$s['anticretico'], '#e67e22'], 'Inactivas' => [$s['inactive'], '#95a5a6']]) ?>

<?= $box('Gestión comercial · ' . \Aero\Realestate\Classes\Stats::PERIODS[$stats->days]) ?>
<div style="display:flex;gap:12px;flex-wrap:wrap">
    <?php $card('Interesados nuevos', (int) $c['total'], (int) $c['new_clients'] . ' clientes nuevos');
    $card('Conversión', $c['rate'] === null ? '—' : e(rtrim(rtrim(number_format($c['rate'], 1, ',', ''), '0'), ',')) . ' %', (int) $c['won'] . ' cerradas · ' . (int) $c['lost'] . ' perdidas · ' . (int) $c['open'] . ' abiertas');
    $card('Visitas realizadas', (int) $v['done'], (int) $v['scheduled'] . ' programadas · ' . (int) $v['cancelled'] . ' canceladas');
    $card('Próximas visitas', (int) $v['upcoming'], (int) $v['requested'] . ' por confirmar', $v['requested'] ? 'warn' : '');
    $card('Días en el mercado', $mk['closed_avg'] === null ? '—' : (int) $mk['closed_avg'] . ' d', $mk['closed_n'] ? 'hasta cerrar (' . (int) $mk['closed_n'] . ' operaciones)' : 'sin cierres en el periodo');
    $card('Antigüedad de lo disponible', $mk['live_avg'] === null ? '—' : (int) $mk['live_avg'] . ' d', (int) $mk['live_n'] . ' propiedades publicadas'); ?>
</div>
<div class="row" style="margin-top:18px"><div class="col-md-7">
    <strong>Mes a mes</strong>
    <?= Charts::columns($d['monthly']['labels'], ['Interesados nuevos' => $d['monthly']['leads'], 'Operaciones cerradas' => $d['monthly']['deals'], 'Visitas realizadas' => $d['monthly']['visits']], ['#3498db', '#2ecc71', '#e67e22']) ?>
</div><div class="col-md-5">
    <strong>Embudo del periodo</strong>
    <?= Charts::hbars(array_map(fn ($st, $label) => ['k' => $label, 'n' => (int) ($c['by_stage'][$st] ?? 0)], array_keys(\Aero\Realestate\Models\Lead::$stages), \Aero\Realestate\Models\Lead::$stages), '#9b59b6') ?>
</div></div>

<?php if ($canMoney): ?>
<?= $box('Comisiones generadas · ' . \Aero\Realestate\Classes\Stats::PERIODS[$stats->days]) ?>
<?php if (!$d['commissions']): ?><p class="text-muted">Sin comisiones en el periodo.</p><?php else: ?>
<div style="display:flex;gap:12px;flex-wrap:wrap">
<?php foreach ($d['commissions'] as $cur => $x): $card('Comisión · ' . $cur, $money($x['total']), 'agentes ' . $money($x['agents']) . ' · pagado ' . $money($x['paid']) . ' · pendiente ' . $money($x['pending']) . ' (' . (int) $x['n'] . ' operaciones)'); endforeach ?>
</div><?php endif ?>
<?php endif ?>

<?= $box('Anticréticos') ?>
<div style="display:flex;gap:12px;flex-wrap:wrap">
    <?php $k = $d['contracts'];
    $card('Vigentes', (int) $k['active']); $card('Por vencer (' . \Aero\Realestate\Models\Contract::EXPIRING_DAYS . ' d)', (int) $k['expiring'], '', $k['expiring'] ? 'warn' : '');
    $card('Vencidos', (int) $k['overdue'], '', $k['overdue'] ? 'bad' : ''); $card('En disputa', (int) $k['dispute'], '', $k['dispute'] ? 'bad' : '');
    foreach ($d['capital'] as $cur => $x) { $card('Capital vigente · ' . $cur, $money($x['capital']), 'por devolver ' . $money($x['pending']) . ' · ' . (int) $x['n'] . ' contratos'); } ?>
</div>
<?php if (!$d['capital']): ?><p class="text-muted" style="margin-top:8px">Sin anticréticos vigentes.</p><?php else: ?><p class="text-muted" style="margin-top:8px;font-size:12px">Cada moneda por separado: no se convierten entre sí.</p><?php endif ?>

<?= $box('Alquileres y anticréticos por vencer (60 días) y vencidos') ?>
<?php if (!$d['expiring']): ?><p class="text-muted">Nada por vencer en los próximos 60 días.</p><?php else: ?>
<div class="table-responsive"><table class="table data"><thead><tr><th>Tipo</th><th>Contrato</th><th>Propiedad</th><th>Cliente</th><th>Monto</th><th>Vence</th></tr></thead><tbody>
<?php foreach ($d['expiring'] as $e): ?>
    <tr><td><?= e($e['kind']) ?></td><td><a href="<?= $link(str_replace('aero/realestate/', '', $e['url'])) ?>"><?= e($e['code']) ?></a></td><td><?= e($e['title']) ?></td><td><?= e($e['party']) ?></td><td><?= e($e['amount']) ?></td>
    <td><?= e($e['end']->format('d/m/Y')) ?> <small class="<?= $e['left'] < 0 ? 'text-danger' : 'text-muted' ?>">(<?= $e['left'] >= 0 ? $e['left'] . ' d' : 'vencido hace ' . abs($e['left']) . ' d' ?>)</small></td></tr>
<?php endforeach ?></tbody></table></div><?php endif ?>

<div class="row"><div class="col-md-6">
    <?= $box('Propiedades más vistas') ?>
    <?php if (!$d['top']): ?><p class="text-muted">Aún sin visitas al portal.</p><?php else: ?>
    <table class="table data"><thead><tr><th>Propiedad</th><th>Vistas</th><th>Visitas agendadas</th></tr></thead><tbody>
    <?php foreach ($d['top'] as $t): ?><tr><td><a href="<?= $link('properties/update/' . $t['id']) ?>"><?= e($t['title']) ?></a><br><small class="text-muted"><?= e($t['where']) ?></small></td><td><?= (int) $t['views'] ?></td><td><?= (int) $t['visits'] ?></td></tr><?php endforeach ?>
    </tbody></table><?php endif ?>
</div><div class="col-md-6">
    <?= $box('Demanda por ciudad') ?><?= Charts::hbars($d['demand']['city'], '#1abc9c') ?>
    <?= $box('Demanda por zona') ?><?= Charts::hbars($d['demand']['zone'], '#3498db') ?>
    <?= $box('Demanda por tipo de propiedad') ?><?= Charts::hbars($d['demand']['type'], '#e67e22') ?>
    <p class="text-muted" style="font-size:12px;margin-top:8px">Oportunidades creadas en el periodo según la propiedad de interés.</p>
</div></div>
