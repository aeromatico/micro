<?php
$fmt = fn ($n) => number_format((float) $n, 2, ',', '.');
$url = fn ($c) => Backend::url('aero/realestate/contracts/update/' . $c->id);
$rows = function ($list, $empty) use ($fmt, $url) { ?>
    <?php if ($list->isEmpty()): ?>
        <p class="text-muted"><?= e($empty) ?></p>
    <?php else: ?>
    <div class="table-responsive"><table class="table data">
        <thead><tr><th>Contrato</th><th>Propiedad</th><th>Anticresista</th><th>Propietario</th><th>Capital</th><th>Por devolver</th><th>Vence</th><th>Estado</th></tr></thead>
        <tbody>
        <?php foreach ($list as $c): ?>
            <tr>
                <td><a href="<?= $url($c) ?>"><?= e($c->code) ?></a></td>
                <td><?= e($c->property?->title) ?></td>
                <td><?= e($c->client?->name) ?></td>
                <td><?= e($c->owner?->name) ?></td>
                <td><?= $fmt($c->capital) ?> <?= e($c->currency) ?></td>
                <td><?= $fmt($c->pending_amount) ?></td>
                <td><?= e($c->end_date?->format('d/m/Y')) ?> <?php if ($c->days_left !== null): ?><small class="text-muted">(<?= $c->days_left >= 0 ? $c->days_left . ' d' : 'vencido ' . abs($c->days_left) . ' d' ?>)</small><?php endif ?></td>
                <td><?= e($c->status_label) ?> · <?= e($c->return_status_name) ?></td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table></div>
    <?php endif ?>
<?php };
?>
<div class="row" style="margin-bottom:20px">
    <?php foreach ([
        ['Vigentes', $counts['active']], ['Por vencer (' . \Aero\Realestate\Models\Contract::EXPIRING_DAYS . ' días)', $counts['expiring']],
        ['Vencidos', $counts['overdue']], ['En disputa', $counts['dispute']], ['Borradores y reservas', $counts['draft']],
    ] as [$label, $n]): ?>
        <div class="col-md-2 col-sm-4" style="margin-bottom:10px">
            <div class="callout callout-info" style="margin:0"><div class="content"><div class="text-muted"><?= e($label) ?></div><h2 style="margin:0"><?= (int) $n ?></h2></div></div>
        </div>
    <?php endforeach ?>
</div>

<h4>Capital en poder de los propietarios (vigentes)</h4>
<?php if ($capitalByCurrency->isEmpty()): ?>
    <p class="text-muted">Sin contratos vigentes.</p>
<?php else: ?>
    <p>
    <?php foreach ($capitalByCurrency as $r): ?>
        <span class="label label-info" style="font-size:14px;margin-right:8px"><?= $fmt($r->pending) ?> <?= e($r->currency) ?> · <?= (int) $r->n ?> contratos</span>
    <?php endforeach ?>
    </p>
    <p class="text-muted">Cada moneda por separado: no se convierten entre sí.</p>
<?php endif ?>

<h4 style="margin-top:25px">Próximos a vencer y vencidos</h4>
<?php $rows($expiring, 'Ningún contrato vence en los próximos ' . \Aero\Realestate\Models\Contract::EXPIRING_DAYS . ' días.') ?>

<h4 style="margin-top:25px">Devoluciones parciales</h4>
<?php $rows($returns, 'Sin devoluciones parciales.') ?>

<h4 style="margin-top:25px">En disputa</h4>
<?php $rows($disputes, 'Sin disputas.') ?>
