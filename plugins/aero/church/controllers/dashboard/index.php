<?php
$fmt = fn ($n) => number_format((float) $n, 0, ',', '.');
$money = fn ($v) => number_format((float) $v, 2, ',', '.');
$delta = function ($x) { return $x === null ? '<span class="text-muted">sin base</span>' : '<span class="' . ($x >= 0 ? 'text-success' : 'text-danger') . '">' . ($x > 0 ? '+' : '') . $x . '%</span>'; };
$card = function ($title, $big, $sub = '') { return '<div style="display:inline-block;vertical-align:top;width:230px;margin:0 14px 14px 0;padding:14px;border:1px solid #d9d9d9;border-radius:6px"><div class="text-muted" style="font-size:12px">' . $title . '</div><div style="font-size:26px;font-weight:bold">' . $big . '</div><div style="font-size:12px">' . $sub . '</div></div>'; };
$maxGrowth = max(1, ...array_column($d['growth'], 1));
$maxWeek = max(1, ...array_values($d['weekly']));
?>
<div>
    <?= $card('Miembros activos', $fmt($d['active_members']), 'de ' . $fmt($d['total_members']) . ' registrados') ?>
    <?= $card('Nuevos este mes', $fmt($d['new_members']['now']), 'vs. mes anterior (' . $fmt($d['new_members']['prev']) . '): ' . $delta($d['new_members']['delta'])) ?>
    <?= $card('Visitantes este mes', $fmt($d['visitors']['now']), 'integrados: ' . $d['visitors']['rate'] . '%') ?>
    <?= $card('Asistencia (28 días)', $fmt($d['attendance']['now']), 'periodo anterior ' . $fmt($d['attendance']['prev']) . ': ' . $delta($d['attendance']['delta'])) ?>
    <?= $card('Eventos en 14 días', $fmt($d['upcoming_events']), '') ?>
    <?php if (isset($d['giving'])): ?>
        <?php foreach ($d['giving']['now'] as $cur => $t): $p = $d['giving']['prev'][$cur]['total'] ?? 0; ?>
            <?= $card('Aportes del mes (' . e($cur) . ')', $money($t['total']), 'mes anterior ' . $money($p) . ': ' . $delta(\Aero\Church\Classes\ReportService::delta($t['total'], $p))) ?>
        <?php endforeach ?>
        <?php if (!$d['giving']['now']): ?><?= $card('Aportes del mes', '0,00', 'sin aportes confirmados') ?><?php endif ?>
    <?php endif ?>
    <?php if (isset($d['prayers_open'])): ?>
        <?= $card('Peticiones de oración abiertas', $fmt($d['prayers_open']), '<a href="' . Backend::url('aero/church/prayers') . '">Ver</a>') ?>
        <?= $card('Visitantes por atender', $fmt($d['visits_pending']), '<a href="' . Backend::url('aero/church/visits') . '">Ver</a>') ?>
    <?php endif ?>
</div>

<div style="display:flex;flex-wrap:wrap;gap:40px;margin-top:10px">
    <div>
        <h4>Altas de miembros (12 meses)</h4>
        <table style="border-collapse:collapse">
        <?php foreach ($d['growth'] as [$month, $n, $acc]): ?>
            <tr><td style="padding:2px 10px 2px 0;font-size:12px"><?= e($month) ?></td>
                <td><div style="background:#3b82f6;height:12px;width:<?= max(1, round($n / $maxGrowth * 220)) ?>px;display:inline-block;vertical-align:middle"></div> <?= (int) $n ?></td></tr>
        <?php endforeach ?>
        </table>
    </div>
    <div>
        <h4>Asistencia por semana</h4>
        <table style="border-collapse:collapse">
        <?php foreach ($d['weekly'] as $week => $n): ?>
            <tr><td style="padding:2px 10px 2px 0;font-size:12px">sem. <?= e(substr($week, 5)) ?></td>
                <td><div style="background:#10b981;height:12px;width:<?= max(1, round($n / $maxWeek * 220)) ?>px;display:inline-block;vertical-align:middle"></div> <?= (int) $n ?></td></tr>
        <?php endforeach ?>
        </table>
    </div>
    <div>
        <h4>Cumpleaños de la semana</h4>
        <?php if (!$d['birthdays']): ?><p class="text-muted">Ninguno en los próximos 7 días.</p><?php endif ?>
        <ul style="padding-left:18px"><?php foreach ($d['birthdays'] as $b): ?><li><?= e($b['date']) ?> — <?= e($b['name']) ?></li><?php endforeach ?></ul>
    </div>
</div>
<p style="margin-top:20px"><a class="btn btn-default" href="<?= Backend::url('aero/church/reports') ?>">Ver reportes y exportar</a></p>
