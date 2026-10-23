<?php
$money = fn ($v) => number_format((float) $v, 2, ',', '.');
$methods = \Aero\Church\Models\Contribution::METHODS;
$totals = $stats->totals();
$monthly = $stats->monthly();
$max = 0;
foreach ($monthly as $m) { foreach ($m as $v) { $max = max($max, $v); } }
?>
<form method="get" class="form-inline" style="margin-bottom:15px">
    <label>Desde <input type="date" name="from" value="<?= e($from) ?>" class="form-control"></label>
    <label>Hasta <input type="date" name="to" value="<?= e($to) ?>" class="form-control"></label>
    <label>Sede
        <select name="branch" class="form-control custom-select">
            <option value="">Todas</option>
            <?php foreach ($branches as $id => $name): ?><option value="<?= (int) $id ?>" <?= (int) $branchId === (int) $id ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach ?>
        </select>
    </label>
    <button class="btn btn-primary" type="submit">Ver</button>
</form>

<h4>Total del periodo</h4>
<?php if (!$totals): ?>
    <p class="text-muted">No hay aportes confirmados en este periodo.</p>
<?php else: ?>
    <?php foreach ($totals as $cur => $t): ?>
        <p style="font-size:22px"><strong><?= $money($t['total']) ?> <?= e($cur) ?></strong> <small class="text-muted">en <?= (int) $t['count'] ?> aportes</small></p>
    <?php endforeach ?>
<?php endif ?>

<?php foreach (['Por concepto' => $stats->byFund(), 'Por sede' => $stats->byBranch(), 'Por forma de pago' => $stats->byMethod()] as $title => $rows): ?>
    <h4 style="margin-top:20px"><?= e($title) ?></h4>
    <table class="table data" style="max-width:520px">
        <?php foreach ($rows as $r): ?>
            <tr><td><?= e($methods[$r->label] ?? $r->label) ?></td><td class="text-right"><?= $money($r->total) ?> <?= e($r->currency) ?></td></tr>
        <?php endforeach ?>
        <?php if (!$rows): ?><tr><td class="text-muted">Sin datos.</td></tr><?php endif ?>
    </table>
<?php endforeach ?>

<h4 style="margin-top:20px">Evolución de los últimos 12 meses</h4>
<table class="table data" style="max-width:720px">
    <?php foreach ($monthly as $ym => $byCur): ?>
        <tr>
            <td style="width:90px"><?= e($ym) ?></td>
            <td>
                <?php foreach ($byCur as $cur => $v): ?>
                    <div style="background:#3b82f6;height:12px;width:<?= $max > 0 ? max(1, round($v / $max * 100)) : 0 ?>%;display:inline-block"></div>
                    <?= $money($v) ?> <?= e($cur) ?><br>
                <?php endforeach ?>
                <?php if (!$byCur): ?><span class="text-muted">—</span><?php endif ?>
            </td>
        </tr>
    <?php endforeach ?>
</table>
