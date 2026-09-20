<div class="layout-row">
    <p>
        Últimos <strong><?= $days ?></strong> días ·
        <?php foreach ([7, 30, 90] as $d): ?>
            <a href="?days=<?= $d ?>"><?= $d ?> d</a>&nbsp;
        <?php endforeach ?>
    </p>

<?php if ($isAdmin): ?>
    <table class="table data">
        <thead>
            <tr><th>Tenant</th><th>API key</th><th>Mensajes</th><th>Segmentos</th><th>Entregados</th><th>Fallidos</th><th>Créditos netos</th></tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= e($r->tenant_label) ?></td>
                <td><?= e($r->consumer ?: '—') ?></td>
                <td><?= (int) $r->messages ?></td>
                <td><?= (int) $r->segments ?></td>
                <td><?= (int) $r->delivered ?></td>
                <td><?= (int) $r->failed ?></td>
                <td><?= (int) $r->credits ?></td>
            </tr>
        <?php endforeach ?>
        <?php if ($rows->isEmpty()): ?><tr><td colspan="7">Sin consumo en este período.</td></tr><?php endif ?>
        </tbody>
    </table>
<?php else: ?>
    <?php if ($balance !== null): ?>
        <div class="callout callout-info">
            <div class="content">
                Saldo: <strong><?= number_format((int) $balance) ?></strong> créditos
                <?php if ($perSegment): ?> · <?= $perSegment ?> por segmento<?php endif ?>.
                Los mensajes que fallan o no se entregan se te reembolsan automáticamente.
            </div>
        </div>
    <?php endif ?>

    <h4>Por día</h4>
    <table class="table data">
        <thead><tr><th>Día</th><th>Mensajes</th><th>Segmentos</th><th>Entregados</th><th>Fallidos</th><th>Créditos netos</th></tr></thead>
        <tbody>
        <?php foreach ($byDay as $r): ?>
            <tr><td><?= e($r->day) ?></td><td><?= (int) $r->messages ?></td><td><?= (int) $r->segments ?></td><td><?= (int) $r->delivered ?></td><td><?= (int) $r->failed ?></td><td><?= (int) $r->credits ?></td></tr>
        <?php endforeach ?>
        <?php if ($byDay->isEmpty()): ?><tr><td colspan="6">Sin consumo en este período.</td></tr><?php endif ?>
        </tbody>
    </table>

    <h4>Por origen <small>(API key o panel)</small></h4>
    <table class="table data">
        <thead><tr><th>Origen</th><th>Mensajes</th><th>Segmentos</th><th>Créditos netos</th></tr></thead>
        <tbody>
        <?php foreach ($byKey as $r): ?>
            <tr><td><?= e($r->consumer) ?></td><td><?= (int) $r->messages ?></td><td><?= (int) $r->segments ?></td><td><?= (int) $r->credits ?></td></tr>
        <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>
</div>
