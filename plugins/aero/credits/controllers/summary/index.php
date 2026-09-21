<?php $n = fn ($v) => number_format($v, 0, ',', '.'); ?>
<style>.sm td,.sm th{padding:8px 10px;border-bottom:1px solid #eef1f5}.sm th{font-size:11px;text-transform:uppercase;color:#8492a6;text-align:right}.sm td{text-align:right}.sm td:first-child,.sm th:first-child{text-align:left}.sm-dot{width:10px;height:10px;border-radius:50%;display:inline-block;margin-right:6px}.sm-box{border:1px solid #e0e6ed;border-radius:8px;padding:14px 18px;background:#fff;margin-bottom:16px}</style>

<div class="sm-box">
    <?php if (!$problems): ?>
        <strong style="color:#15803d">✔ Contabilidad perfecta:</strong> saldos, cadena de saldos, acumulados, intercambios y reembolsos cuadran con el libro.
    <?php else: ?>
        <strong style="color:#b91c1c">✖ La auditoría encontró problemas:</strong>
        <ul><?php foreach ($problems as $p): ?><li><?= e($p) ?></li><?php endforeach ?></ul>
    <?php endif ?>
</div>

<div class="sm-box">
    Recargas cobradas: <strong>Bs <?= number_format($revenue, 2, ',', '.') ?></strong>
    · este mes: <strong>Bs <?= number_format($revenueMonth, 2, ',', '.') ?></strong>
    · QR pendientes de pago: <strong><?= $pending ?></strong>
    <?php if ($review->count()): ?>
        <div style="margin-top:8px;color:#b45309">⚠ <?= $review->count() ?> recarga(s) con pago por menos del monto — requieren revisión manual:
            <?php foreach ($review as $r): ?>#<?= $r->id ?> (tenant <?= $r->tenant_id ?>, Bs <?= $r->amount_bob ?>) <?php endforeach ?></div>
    <?php endif ?>
</div>

<div class="sm-box" style="padding:0;overflow:auto">
<table class="sm" style="width:100%;border-collapse:collapse">
    <thead><tr>
        <th>Moneda</th><th>Activas hoy</th><th>Vendidas</th><th>Regaladas</th><th>En planes</th><th>Ajustes +</th><th>Reembolsos</th>
        <th>Consumidas</th><th>Consumo hoy</th><th>Comisión intercambio</th><th>Ajustes −</th>
    </tr></thead>
    <tbody>
    <?php foreach ($summary as $s): ?>
        <tr>
            <td><span class="sm-dot" style="background:<?= e($s['color']) ?>"></span><?= e($s['label']) ?></td>
            <td><strong><?= $n($s['active']) ?></strong></td>
            <td><?= $n($s['sold']) ?></td><td><?= $n($s['gifted']) ?></td><td><?= $n($s['plan_grant']) ?></td>
            <td><?= $n($s['adjust_in']) ?></td><td><?= $n($s['refunded']) ?></td>
            <td><?= $n($s['consumed']) ?></td><td><?= $n($s['consumed_today']) ?></td>
            <td><?= $n($s['fees']) ?></td><td><?= $n($s['adjust_out']) ?></td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>
</div>
<p class="help-block">Activas = monedas que hoy tienen los tenants (suma de saldos). El intercambio entre monedas no cambia el total emitido: sale de una moneda y entra en otra, y la comisión se retira de circulación.</p>
