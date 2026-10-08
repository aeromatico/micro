<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/finance/reports') ?>">Finanzas</a></li><li>Libro mayor y reportes</li></ul>
<?php Block::endPut() ?>
<?php $n = fn ($v) => number_format((float) $v, 2); ?>
<form method="get" class="form-inline" style="margin-bottom:16px">
    Del <input type="date" name="from" value="<?= $from->toDateString() ?>" class="form-control" style="width:auto">
    al <input type="date" name="to" value="<?= $to->toDateString() ?>" class="form-control" style="width:auto">
    Cuenta del mayor
    <select name="account" class="form-control" style="width:auto">
        <?php foreach ($accounts as $a): ?><option value="<?= $a->id ?>" <?= $a->id == $accountId ? 'selected' : '' ?>><?= e($a->label) ?></option><?php endforeach ?>
    </select>
    <button class="btn btn-primary">Ver</button>
</form>

<?php $inc = array_sum(array_column(array_filter($categories, fn ($c) => $c['type'] === 'income'), 'amount')); $exp = array_sum(array_column(array_filter($categories, fn ($c) => $c['type'] === 'expense'), 'amount')); ?>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin-bottom:20px">
    <?php foreach ([['Ingresos', $inc, ''], ['Egresos', $exp, ''], ['Resultado', $inc - $exp, $inc - $exp >= 0 ? '#2e9e5b' : '#c0392b']] as [$l, $v, $c]): ?>
        <div style="background:color-mix(in srgb, var(--bs-body-color) 5%, var(--bs-body-bg));border:1px solid var(--bs-border-color);border-radius:6px;padding:12px"><div style="color:var(--bs-secondary-color);font-size:12px"><?= e($l) ?> (Bs)</div><div style="font-size:26px;font-weight:700;color:<?= $c ?>"><?= $n($v) ?></div></div>
    <?php endforeach ?>
</div>

<h4>Por categoría</h4>
<table class="table data">
    <thead><tr><th>Cuenta</th><th>Tipo</th><th style="text-align:right">Importe (Bs)</th></tr></thead>
    <tbody>
    <?php foreach ($categories as $c): ?>
        <tr><td><?= e($c['code'] . ' · ' . $c['name']) ?></td><td><?= $c['type'] === 'income' ? 'Ingreso' : 'Egreso' ?></td><td style="text-align:right"><?= $n($c['amount']) ?></td></tr>
    <?php endforeach ?>
    <?php if (!$categories): ?><tr><td colspan="3" class="text-muted">Sin movimientos en el rango.</td></tr><?php endif ?>
    </tbody>
</table>

<h4>Por mes</h4>
<table class="table data">
    <thead><tr><th>Mes</th><th style="text-align:right">Ingresos</th><th style="text-align:right">Egresos</th><th style="text-align:right">Resultado</th></tr></thead>
    <tbody>
    <?php foreach ($monthly as $ym => $m): ?>
        <tr><td><?= e($ym) ?></td><td style="text-align:right"><?= $n($m['income']) ?></td><td style="text-align:right"><?= $n($m['expense']) ?></td>
            <td style="text-align:right;font-weight:600;color:<?= $m['result'] >= 0 ? '#2e9e5b' : '#c0392b' ?>"><?= $n($m['result']) ?></td></tr>
    <?php endforeach ?>
    </tbody>
</table>

<h4>Libro mayor<?= $ledger && $ledger['account'] ? ': ' . e($ledger['account']->label) : '' ?></h4>
<?php if ($ledger && $ledger['account']): ?>
<table class="table data">
    <thead><tr><th>Fecha</th><th>Asiento</th><th>Descripción</th><th style="text-align:right">Debe</th><th style="text-align:right">Haber</th><th style="text-align:right">Saldo</th></tr></thead>
    <tbody>
        <tr><td colspan="5"><em>Saldo anterior</em></td><td style="text-align:right"><?= $n($ledger['opening']) ?></td></tr>
    <?php foreach ($ledger['rows'] as $row): ?>
        <tr><td><?= e(substr((string) $row['date'], 0, 10)) ?></td><td>#<?= $row['number'] ?></td><td><?= e($row['description']) ?></td>
            <td style="text-align:right"><?= $row['debit'] > 0 ? $n($row['debit']) : '' ?></td><td style="text-align:right"><?= $row['credit'] > 0 ? $n($row['credit']) : '' ?></td><td style="text-align:right"><?= $n($row['balance']) ?></td></tr>
    <?php endforeach ?>
        <tr><th colspan="5">Saldo final</th><th style="text-align:right"><?= $n($ledger['closing']) ?></th></tr>
    </tbody>
</table>
<?php endif ?>

<h4>Balance de sumas y saldos al <?= $to->format('d/m/Y') ?></h4>
<table class="table data">
    <thead><tr><th>Cuenta</th><th style="text-align:right">Suma debe</th><th style="text-align:right">Suma haber</th><th style="text-align:right">Saldo deudor</th><th style="text-align:right">Saldo acreedor</th></tr></thead>
    <tbody>
    <?php foreach ($trial['rows'] as $row): ?>
        <tr><td><?= e($row['code'] . ' · ' . $row['name']) ?></td><td style="text-align:right"><?= $n($row['debit']) ?></td><td style="text-align:right"><?= $n($row['credit']) ?></td><td style="text-align:right"><?= $n($row['debtor']) ?></td><td style="text-align:right"><?= $n($row['creditor']) ?></td></tr>
    <?php endforeach ?>
        <tr><th>Totales</th><th style="text-align:right"><?= $n($trial['totals']['debit']) ?></th><th style="text-align:right"><?= $n($trial['totals']['credit']) ?></th><th style="text-align:right"><?= $n($trial['totals']['debtor']) ?></th><th style="text-align:right"><?= $n($trial['totals']['creditor']) ?></th></tr>
    </tbody>
</table>
