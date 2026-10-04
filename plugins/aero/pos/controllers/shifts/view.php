<?php /** @var Aero\Pos\Controllers\Shifts $this */
$m = fn ($n) => number_format((float) $n, 2);
$open = $shift->isOpen();
$kinds = ['cash' => 'Efectivo', 'qr' => 'QR', 'card' => 'Tarjeta', 'transfer' => 'Transferencia', 'other' => 'Otro'];
?>
<style>
.pos-rep { max-width: 880px; }
.pos-rep h3 { margin: 0 0 4px; }
.pos-rep .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:10px; margin:14px 0; }
.pos-rep .kpi { background:#f4f5f7; border-radius:8px; padding:12px; }
.pos-rep .kpi b { display:block; font-size:20px; }
.pos-rep .kpi span { font-size:12px; color:#6b7280; text-transform:uppercase; letter-spacing:.04em; }
.pos-rep table { width:100%; margin-bottom:16px; }
.pos-rep th, .pos-rep td { padding:6px 8px; border-bottom:1px solid #e5e7eb; text-align:left; }
.pos-rep td.n, .pos-rep th.n { text-align:right; font-variant-numeric:tabular-nums; }
.pos-rep .diff-bad { color:#dc2626; font-weight:700; } .pos-rep .diff-ok { color:#16a34a; font-weight:700; }
.pos-rep .panel { border:1px solid #e5e7eb; border-radius:8px; padding:14px; margin-bottom:16px; }
@media print { .pos-noprint { display:none !important; } .pos-rep { max-width:none; } }
</style>
<div class="padded-container pos-rep">
    <p class="pos-noprint"><a href="<?= Backend::url('aero/pos/shifts') ?>"><i class="icon-arrow-left"></i> Turnos</a></p>

    <h3>Turno #<?= $shift->id ?> · <?= e($shift->terminal?->name) ?>
        <span class="label label-<?= $open ? 'success' : 'default' ?>" style="font-size:12px"><?= $open ? 'Abierto' : 'Cerrado' ?></span>
    </h3>
    <p class="text-muted">
        Abrió <?= e(trim(($shift->opener?->first_name ?? '') . ' ' . ($shift->opener?->last_name ?? ''))) ?> el <?= $shift->opened_at?->format('d/m/Y H:i') ?>
        <?php if ($shift->closed_at): ?> · Cerró <?= e(trim(($shift->closer?->first_name ?? '') . ' ' . ($shift->closer?->last_name ?? ''))) ?> el <?= $shift->closed_at->format('d/m/Y H:i') ?><?php endif ?>
    </p>

    <div class="grid">
        <div class="kpi"><span>Ventas</span><b><?= (int) $report['sales_count'] ?></b></div>
        <div class="kpi"><span>Total vendido</span><b><?= $m($report['sales_total']) ?></b></div>
        <div class="kpi"><span>Cobrado</span><b><?= $m($report['paid_total']) ?></b></div>
        <div class="kpi"><span>Propinas</span><b><?= $m($report['tips']) ?></b></div>
        <div class="kpi"><span>Descuentos</span><b><?= $m($report['discounts']) ?></b></div>
        <div class="kpi"><span>Anuladas</span><b><?= (int) $report['voided_count'] ?></b></div>
    </div>

    <h4>Cobros por método</h4>
    <table>
        <thead><tr><th>Método</th><th class="n">Cobros</th><th class="n">Total</th></tr></thead>
        <tbody>
        <?php foreach ($report['by_method'] as $row): ?>
            <tr><td><?= e($row['label']) ?></td><td class="n"><?= (int) $row['count'] ?></td><td class="n"><?= $m($row['total']) ?></td></tr>
        <?php endforeach ?>
        <?php if (!$report['by_method']): ?><tr><td colspan="3" class="text-muted">Sin cobros todavía.</td></tr><?php endif ?>
        </tbody>
    </table>

    <h4>Arqueo de efectivo</h4>
    <table>
        <tr><td>Efectivo inicial</td><td class="n"><?= $m($report['opening_cash']) ?></td></tr>
        <tr><td>+ Cobros en efectivo</td><td class="n"><?= $m(array_sum(array_map(fn ($r) => $r['kind'] === 'cash' ? $r['total'] : 0, $report['by_method']))) ?></td></tr>
        <tr><td>+ Ingresos</td><td class="n"><?= $m($report['cash_in']) ?></td></tr>
        <tr><td>− Egresos</td><td class="n"><?= $m($report['cash_out']) ?></td></tr>
        <tr><th>Efectivo esperado</th><th class="n"><?= $m($report['expected_cash']) ?></th></tr>
        <?php if (!$open): ?>
            <tr><td>Efectivo contado</td><td class="n"><?= $m($report['counted_cash']) ?></td></tr>
            <tr><th>Diferencia</th><th class="n <?= abs((float) $report['difference']) < 0.005 ? 'diff-ok' : 'diff-bad' ?>"><?= ($report['difference'] > 0 ? '+' : '') . $m($report['difference']) ?></th></tr>
        <?php endif ?>
    </table>

    <?php if ($movements->count()): ?>
        <h4>Movimientos de efectivo</h4>
        <table>
            <thead><tr><th>Hora</th><th>Tipo</th><th>Motivo</th><th>Registró</th><th class="n">Monto</th></tr></thead>
            <tbody>
            <?php foreach ($movements as $mv): ?>
                <tr><td><?= $mv->created_at->format('H:i') ?></td><td><?= $mv->type === 'in' ? 'Ingreso' : 'Egreso' ?></td><td><?= e($mv->reason) ?></td>
                    <td><?= e($mv->user?->first_name) ?></td><td class="n"><?= $m($mv->amount) ?></td></tr>
            <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>

    <?php if ($open): ?>
    <div class="pos-noprint">
        <div class="panel">
            <h4>Registrar ingreso o egreso de efectivo</h4>
            <form data-request="onAddMovement" data-request-flash class="form-inline">
                <input type="hidden" name="shift_id" value="<?= $shift->id ?>">
                <select name="type" class="form-control custom-select"><option value="in">Ingreso</option><option value="out">Egreso</option></select>
                <input type="number" step="0.01" min="0.01" name="amount" class="form-control" placeholder="Monto" required style="width:120px">
                <input type="text" name="reason" class="form-control" placeholder="Motivo (ej. compra de hielo)" required style="min-width:240px">
                <button type="submit" class="btn btn-default">Registrar</button>
            </form>
        </div>
        <div class="panel">
            <h4>Cerrar turno</h4>
            <p class="text-muted">Cuenta el efectivo que hay en caja y escríbelo. El sistema calcula la diferencia con lo esperado (<?= $m($report['expected_cash']) ?>).</p>
            <form data-request="onCloseShift" data-request-flash data-request-confirm="¿Cerrar el turno? Ya no se podrá vender en esta caja hasta abrir otro." class="form-inline">
                <input type="hidden" name="shift_id" value="<?= $shift->id ?>">
                <input type="number" step="0.01" min="0" name="counted_cash" class="form-control" placeholder="Efectivo contado" required style="width:170px">
                <input type="text" name="notes" class="form-control" placeholder="Notas (opcional)" style="min-width:240px">
                <button type="submit" class="btn btn-primary"><i class="icon-check"></i> Cerrar turno</button>
            </form>
        </div>
    </div>
    <?php else: ?>
        <?php if ($shift->notes): ?><p><strong>Notas:</strong> <?= e($shift->notes) ?></p><?php endif ?>
        <button type="button" class="btn btn-default pos-noprint" onclick="window.print()"><i class="icon-print"></i> Imprimir reporte</button>
    <?php endif ?>
</div>
