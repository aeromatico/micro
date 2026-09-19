<div class="layout-row">
    <p>
        Últimos <strong><?= $days ?></strong> días ·
        <?php foreach ([7, 30, 90] as $d): ?>
            <a href="?days=<?= $d ?>"><?= $d ?> d</a>&nbsp;
        <?php endforeach ?>
    </p>
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
</div>
