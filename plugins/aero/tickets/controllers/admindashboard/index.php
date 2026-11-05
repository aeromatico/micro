<?php
use Aero\Tickets\Classes\Currency;
$m = fn (array $a) => $a ? implode(' + ', array_map(fn ($c, $v) => Currency::format($v, $c), array_keys($a), $a)) : Currency::format(0);
$tn = fn ($id) => $tenants[$id] ?? ('#' . $id);
$kpi = function ($label, $value, $sub = null, $href = null, $warn = false) {
    return '<div class="ad-kpi' . ($warn ? ' warn' : '') . '">' . ($href ? '<a href="' . e($href) . '">' : '') . '<div class="v">' . e($value) . '</div><div class="l">' . e($label) . '</div>' . ($sub ? '<div class="s">' . e($sub) . '</div>' : '') . ($href ? '</a>' : '') . '</div>';
};
$act = $s['activity'];
$max = max(1, max(array_map(fn ($d) => max($d['orders'], $d['tickets'], $d['scans']), $act)));
?>
<style>
.ad-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:12px;margin:0 0 18px}
.ad-kpi{background:#fff;border:1px solid #dfe3ea;border-radius:10px;padding:12px 14px}.ad-kpi.warn{border-color:#f0b4ae;background:#fff7f6}
.ad-kpi .v{font-size:1.7rem;font-weight:700;line-height:1.1}.ad-kpi .l{color:#555;font-size:.9rem}.ad-kpi .s{color:#888;font-size:.8rem;margin-top:2px}.ad-kpi a{color:inherit;text-decoration:none;display:block}
.ad-h{margin:18px 0 8px;font-size:1.05rem}.ad-chart{display:flex;align-items:flex-end;gap:3px;height:90px;background:#fff;border:1px solid #dfe3ea;border-radius:10px;padding:8px}
.ad-chart i{flex:1;display:flex;align-items:flex-end;gap:1px;height:100%}.ad-chart b{display:block;flex:1;border-radius:2px 2px 0 0;min-height:1px}
.ad-leg span{display:inline-block;margin-right:14px;font-size:.85rem}.ad-leg i{display:inline-block;width:10px;height:10px;border-radius:2px;margin-right:4px}
table.ad{width:100%;background:#fff;border:1px solid #dfe3ea;border-radius:10px;border-collapse:separate;border-spacing:0;overflow:hidden}table.ad td,table.ad th{padding:7px 10px;border-bottom:1px solid #eef0f4;text-align:left;font-size:.9rem}
</style>
<div class="padded-container">
<h3 class="ad-h" style="margin-top:0">Usuarios y organizadores</h3>
<div class="ad-grid">
<?= $kpi('Usuarios registrados', $s['users']['total'], $s['users']['backend'] . ' del panel · ' . $s['users']['buyers'] . ' compradores', Backend::url('aero/tickets/platformusers')) ?>
<?= $kpi('Organizadores', $s['organizers']['total'], $s['organizers']['approved'] . ' aprobados · ' . $s['organizers']['suspended'] . ' suspendidos', Backend::url('aero/tickets/organizers')) ?>
<?= $kpi('Organizadores por aprobar', $s['organizers']['pending'], null, Backend::url('aero/tickets/organizers?status=pending'), $s['organizers']['pending'] > 0) ?>
<?= $kpi('Compradores bloqueados', $s['users']['buyers_blocked'], null, Backend::url('aero/tickets/buyers')) ?>
</div>
<h3 class="ad-h">Eventos</h3>
<div class="ad-grid">
<?= $kpi('Publicados', $s['events']['published'], 'publicados + en curso') ?>
<?= $kpi('Activos', $s['events']['active'], 'visibles y vigentes', Backend::url('aero/tickets/adminevents')) ?>
<?= $kpi('Finalizados', $s['events']['finished']) ?>
<?= $kpi('En revisión', $s['events']['in_review'], 'esperan moderación', Backend::url('aero/tickets/adminevents'), $s['events']['in_review'] > 0) ?>
<?= $kpi('Suspendidos', $s['events']['suspended'], null, null, $s['events']['suspended'] > 0) ?>
</div>
<h3 class="ad-h">Ventas y comisiones</h3>
<div class="ad-grid">
<?= $kpi('Entradas emitidas', $s['tickets']['issued'], $s['tickets']['used'] . ' ya ingresaron') ?>
<?= $kpi('Entradas vendidas', $s['tickets']['sold'], $s['tickets']['free'] . ' gratuitas o de cortesía') ?>
<?= $kpi('Ventas brutas', $m($s['sales']['gross']), $s['sales']['orders'] . ' órdenes confirmadas') ?>
<?= $kpi('Comisiones generadas', $m($s['commissions']['accrued']), 'por liquidar', Backend::url('aero/tickets/commissionentries')) ?>
<?= $kpi('Comisiones liquidadas', $m($s['commissions']['settled'])) ?>
</div>
<h3 class="ad-h">Pagos y obligaciones pendientes</h3>
<div class="ad-grid">
<?= $kpi('Pagos por verificar', $s['payments']['to_verify'], $m($s['payments']['to_verify_amount']), Backend::url('aero/tickets/payments?status=submitted'), $s['payments']['to_verify_old'] > 0) ?>
<?= $kpi('Con más de 24 h esperando', $s['payments']['to_verify_old'], null, Backend::url('aero/tickets/payments?status=submitted'), $s['payments']['to_verify_old'] > 0) ?>
<?= $kpi('Reembolsos pendientes', $s['payments']['refund_pending'], $m($s['payments']['refund_amount']), Backend::url('aero/tickets/payments?status=refund_pending'), $s['payments']['refund_pending'] > 0) ?>
<?= $kpi('Pagos tardíos', $s['payments']['late'], null, Backend::url('aero/tickets/payments?flag=late')) ?>
<?= $kpi('Pagos sospechosos abiertos', $s['payments']['suspicious'], null, Backend::url('aero/tickets/payments?flag=suspicious'), $s['payments']['suspicious'] > 0) ?>
<?= $kpi('Órdenes en incidencia', $s['payments']['incidents'], null, Backend::url('aero/tickets/orders?status=incident'), $s['payments']['incidents'] > 0) ?>
</div>
<h3 class="ad-h">Actividad (últimos 30 días)</h3>
<div class="ad-chart" title="órdenes · entradas emitidas · ingresos en puerta">
<?php foreach ($act as $d): ?><i title="<?= e($d['date']) ?>: <?= $d['orders'] ?> órdenes, <?= $d['tickets'] ?> entradas, <?= $d['scans'] ?> ingresos">
    <b style="height:<?= round($d['orders'] / $max * 100) ?>%;background:#4f46e5"></b><b style="height:<?= round($d['tickets'] / $max * 100) ?>%;background:#0891b2"></b><b style="height:<?= round($d['scans'] / $max * 100) ?>%;background:#16a34a"></b></i><?php endforeach ?>
</div>
<p class="ad-leg" style="margin-top:6px"><span><i style="background:#4f46e5"></i>Órdenes</span><span><i style="background:#0891b2"></i>Entradas emitidas</span><span><i style="background:#16a34a"></i>Ingresos en puerta</span>
<span style="color:#888">Total: <?= array_sum(array_column($act, 'orders')) ?> órdenes · <?= array_sum(array_column($act, 'tickets')) ?> entradas · <?= array_sum(array_column($act, 'scans')) ?> ingresos</span></p>

<div class="row" style="display:flex;gap:16px;flex-wrap:wrap">
<div style="flex:1;min-width:280px"><h3 class="ad-h">Eventos con más entradas</h3>
<table class="ad"><tr><th>Evento</th><th>Organizador</th><th>Entradas</th></tr>
<?php foreach ($s['top_events'] as $r): ?><tr><td><a href="<?= Backend::url('aero/tickets/adminevents/update/' . $r->id) ?>"><?= e($r->name) ?></a></td><td><?= e($tn($r->tenant_id)) ?></td><td><?= (int) $r->tickets ?></td></tr><?php endforeach ?>
<?php if (!$s['top_events']): ?><tr><td colspan="3" class="text-muted">Aún sin datos.</td></tr><?php endif ?></table></div>
<div style="flex:1;min-width:280px"><h3 class="ad-h">Organizadores con más ventas</h3>
<table class="ad"><tr><th>Organizador</th><th>Órdenes</th><th>Ventas</th></tr>
<?php foreach ($s['top_organizers'] as $r): ?><tr><td><?= e($tn($r->tenant_id)) ?></td><td><?= (int) $r->orders ?></td><td><?= e(Currency::format($r->sales)) ?></td></tr><?php endforeach ?>
<?php if (!$s['top_organizers']): ?><tr><td colspan="3" class="text-muted">Aún sin datos.</td></tr><?php endif ?></table></div>
<div style="flex:1;min-width:280px"><h3 class="ad-h">Comisiones por liquidar</h3>
<table class="ad"><tr><th>Organizador</th><th>Órdenes</th><th>Comisión</th></tr>
<?php foreach ($s['overdue_commissions'] as $r): ?><tr><td><?= e($tn($r->tenant_id)) ?></td><td><?= (int) $r->orders ?></td><td><?= e(Currency::format($r->pending)) ?></td></tr><?php endforeach ?>
<?php if (!$s['overdue_commissions']): ?><tr><td colspan="3" class="text-muted">Nada pendiente.</td></tr><?php endif ?></table></div>
</div></div>
