<?php $n = fn ($v) => number_format($v, 0, ',', '.'); ?>
<style>
.au{color:var(--bs-body-color)}
.au h4{margin:22px 0 6px}
.au table{width:100%;border-collapse:collapse}
.au th{font-size:11px;text-transform:uppercase;color:var(--bs-secondary-color);text-align:left;padding:6px 8px;border-bottom:1px solid var(--bs-border-color)}
.au td{padding:7px 8px;border-bottom:1px solid var(--bs-border-color)}
.au .r{text-align:right}.au .m{color:var(--bs-secondary-color);font-size:12px}
.au-dot{width:10px;height:10px;border-radius:50%;display:inline-block;margin-right:5px}
.au-bad{color:color-mix(in srgb,var(--bs-danger,#dc3545) 85%,var(--bs-body-color));font-weight:600}
.au-ok{color:color-mix(in srgb,var(--bs-success,#41b862) 85%,var(--bs-body-color));font-weight:600}
</style>
<div class="au">
    <form method="get" style="display:flex;gap:8px;align-items:center">
        <span class="m">Período</span>
        <select name="days" class="form-control custom-select" style="width:auto" onchange="this.form.submit()">
            <?php foreach ([7, 30, 90] as $d): ?><option value="<?= $d ?>" <?= $d === $days ? 'selected' : '' ?>>Últimos <?= $d ?> días</option><?php endforeach ?>
        </select>
    </form>

    <h4>Cobros fallidos <span class="m">(el llamador no los propagó; el tenant no se enteró)</span></h4>
    <?php if ($failures->isEmpty()): ?>
        <p class="au-ok">✔ Ninguno en este período.</p>
    <?php else: ?>
        <table><thead><tr><th>Acción</th><th>Plugin</th><th class="r">Veces</th><th>Último</th><th>Motivo</th></tr></thead><tbody>
        <?php foreach ($failures as $f): ?>
            <tr><td><code><?= e($f->action_code) ?></code></td><td><?= e($f->source_plugin) ?></td><td class="r au-bad"><?= $n($f->n) ?></td><td class="m"><?= e($f->last_at) ?></td><td class="m"><?= e($f->reason) ?></td></tr>
        <?php endforeach ?>
        </tbody></table>
    <?php endif ?>

    <h4>Consumo por plugin</h4>
    <?php if (!$byPlugin): ?><p class="m">Sin cobros en el período.</p><?php else: ?>
        <table><thead><tr><th>Plugin</th><th>Moneda</th><th class="r">Usos</th><th class="r">Créditos netos</th></tr></thead><tbody>
        <?php foreach ($byPlugin as $plugin => $rows): foreach ($rows as $label => $r): ?>
            <tr><td><?= e($plugin) ?></td><td><span class="au-dot" style="background:<?= e($r['color']) ?>"></span><?= e($label) ?></td><td class="r"><?= $n($r['uses']) ?></td><td class="r"><?= $n($r['amount']) ?></td></tr>
        <?php endforeach; endforeach ?>
        </tbody></table>
        <p class="m">Los importes en moneda Bs están en unidades internas.</p>
    <?php endif ?>

    <h4>Se cobra sin fila en el catálogo</h4>
    <?php if ($uncataloged->isEmpty()): ?>
        <p class="au-ok">✔ Todo cobro tiene su acción en el catálogo.</p>
    <?php else: ?>
        <table><thead><tr><th>Código</th><th>Plugin</th><th class="r">Usos</th><th>Estado</th></tr></thead><tbody>
        <?php foreach ($uncataloged as $u): ?>
            <tr><td><code><?= e($u['code']) ?></code></td><td><?= e($u['plugin']) ?></td><td class="r"><?= $n($u['uses']) ?></td>
                <td class="m"><?= $u['dynamic'] ? 'dinámico: el tenant lo ve agrupado' : '<span class="au-bad">sin clasificar: aparece como «Otros»</span>' ?></td></tr>
        <?php endforeach ?>
        </tbody></table>
    <?php endif ?>

    <h4>Se usa pero no está en el tarifario público</h4>
    <?php if ($notPublic->isEmpty()): ?>
        <p class="au-ok">✔ Todo lo que se cobra figura en Tarifas.</p>
    <?php else: ?>
        <table><thead><tr><th>Función</th><th>Plugin</th><th class="r">Usos</th></tr></thead><tbody>
        <?php foreach ($notPublic as $u): ?>
            <tr><td><?= e($u['label']) ?></td><td><?= e($u['plugin']) ?></td><td class="r"><?= $n($u['uses']) ?></td></tr>
        <?php endforeach ?>
        </tbody></table>
        <p class="m">Dale «Nombre público» y activa «Mostrar en el tarifario» en Créditos → Acciones.</p>
    <?php endif ?>

    <h4>Acciones activas con costo 0 <span class="m">(se registran pero no cobran)</span></h4>
    <p class="m"><?= $freeActions->isEmpty() ? 'Ninguna.' : e($freeActions->implode(' · ')) ?></p>

    <h4>Cargos antiguos sin plugin de origen</h4>
    <p class="m"><?= $n($noPlugin) ?> cargo(s). Los nuevos ya lo guardan siempre (se infiere del código); el ledger es inmutable y los anteriores no se corrigen.</p>
</div>
