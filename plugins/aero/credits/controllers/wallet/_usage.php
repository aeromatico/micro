<?php
$fmt = fn ($n) => number_format($n, 0, ',', '.');
$amt = fn ($t, $n) => !empty($t['is_money']) ? 'Bs ' . \Aero\Credits\Classes\Money::format((int) $n, 2, 'ceil') : $fmt($n);
?>
<?php if (!$usage['categories']): ?>
    <p class="wl-note">No hay consumo en los últimos <?= (int) $usage['days'] ?> días.</p>
<?php else: ?>
    <div class="wl-cards">
        <?php foreach ($usage['totals'] as $t): ?>
            <div class="wl-card">
                <div class="lbl"><span class="wl-dot" style="background:<?= e($t['color']) ?>"></span>Consumido · <?= e($t['label']) ?></div>
                <div class="num"><?= e($amt($t, $t['amount'])) ?></div>
                <div class="sub">últimos <?= (int) $usage['days'] ?> días</div>
            </div>
        <?php endforeach ?>
    </div>
    <?php foreach ($usage['categories'] as $cat => $c): ?>
        <h4 style="margin:18px 0 6px"><?= e($cat) ?>
            <span class="wl-note" style="font-weight:400">— <?= e(implode(' · ', array_map(fn ($t) => $amt($t, $t['amount']) . ' ' . $t['label'], $c['totals']))) ?></span></h4>
        <table class="wl-table">
            <thead><tr><th>Función</th><th style="text-align:right">Usos</th><th style="text-align:right">Créditos</th></tr></thead>
            <tbody>
            <?php foreach ($c['rows'] as $r): ?>
                <tr>
                    <td><?= e($r['label']) ?> <span class="wl-note">(<?= e($r['unit']) ?>)</span></td>
                    <td style="text-align:right"><?= $fmt(max(0, $r['uses'])) ?></td>
                    <td style="text-align:right;white-space:nowrap"><span class="wl-dot" style="background:<?= e($r['color']) ?>"></span> <?= e($amt($r, $r['amount'])) ?> <?= e($r['type_label']) ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    <?php endforeach ?>
<?php endif ?>
