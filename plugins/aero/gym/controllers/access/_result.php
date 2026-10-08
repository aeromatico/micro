<?php $ok = $r['granted']; ?>
<div style="padding:24px;border-radius:12px;text-align:center;color:#fff;background:<?= $ok ? '#2e9e5b' : '#c0392b' ?>">
    <div style="font-size:44px;font-weight:700"><?= $ok ? 'ACCESO PERMITIDO' : 'ACCESO DENEGADO' ?></div>
    <?php if ($r['member']): ?><div style="font-size:26px;margin-top:6px"><?= e($r['member']['name']) ?></div><?php endif ?>
    <?php if ($ok): ?>
        <div style="font-size:18px;margin-top:6px">Vence <?= e(\Carbon\Carbon::parse($r['ends_on'])->format('d/m/Y')) ?> (<?= (int) $r['days_left'] ?> días)</div>
        <?php if ($r['session_id']): ?><div style="margin-top:6px">✔ Asistencia a su clase registrada</div><?php endif ?>
        <?php if ($r['days_left'] !== null && $r['days_left'] <= 3): ?><div style="margin-top:6px;font-weight:700">⚠ Está por vencer</div><?php endif ?>
    <?php else: ?>
        <div style="font-size:20px;margin-top:6px"><?= e($r['reason']) ?></div>
    <?php endif ?>
</div>
