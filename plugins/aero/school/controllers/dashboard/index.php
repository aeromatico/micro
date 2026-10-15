<?php $cur = 'Bs.'; $done = count(array_filter($steps, fn ($s) => $s[1])); ?>
<style>
.sch-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;margin-bottom:18px}
.sch-card{background:#fff;border:1px solid #e3e6ea;border-radius:6px;padding:14px 16px}
.sch-card .n{font-size:24px;font-weight:700;line-height:1.2}
.sch-card .l{color:#6b7480;font-size:12px;text-transform:uppercase;letter-spacing:.4px}
.sch-bad{color:#c0392b}.sch-ok{color:#2e9e5b}
.sch-steps li{padding:5px 0}
</style>
<div class="sch-grid">
    <div class="sch-card"><div class="l">Estudiantes inscritos</div><div class="n"><?= (int) $kpis['students'] ?></div></div>
    <div class="sch-card"><div class="l">Cobrado este mes</div><div class="n sch-ok"><?= $cur ?> <?= number_format($kpis['month'], 2) ?></div></div>
    <div class="sch-card"><div class="l">Por cobrar</div><div class="n"><?= $cur ?> <?= number_format($kpis['pending'], 2) ?></div></div>
    <div class="sch-card"><div class="l">En mora</div><div class="n sch-bad"><?= $cur ?> <?= number_format($kpis['overdue'], 2) ?></div>
        <div class="l"><?= (int) $kpis['debtors'] ?> estudiantes</div></div>
    <div class="sch-card"><div class="l">Asistencia de hoy</div>
        <div class="n"><?= (int) ($today['present'] ?? 0) ?> <span style="font-size:13px" class="sch-bad"><?= (int) ($today['absent'] ?? 0) ?> faltas</span></div></div>
</div>

<div class="row">
<div class="col-md-6">
    <h4>Accesos rápidos</h4>
    <p>
        <a class="btn btn-primary" href="<?= Backend::url('aero/school/collect') ?>">Cobrar</a>
        <a class="btn btn-default" href="<?= Backend::url('aero/school/attendance') ?>">Tomar asistencia</a>
        <a class="btn btn-default" href="<?= Backend::url('aero/school/gradebook') ?>">Cargar notas</a>
        <a class="btn btn-default" href="<?= Backend::url('aero/school/announcements/create') ?>">Nuevo comunicado</a>
        <a class="btn btn-default" href="<?= Backend::url('aero/school/reports') ?>">Morosidad</a>
    </p>
    <h4>Deuda vencida por antigüedad</h4>
    <table class="table data">
        <?php foreach ($aging as $range => $amount): ?>
        <tr><td><?= e($range) ?> días</td><td style="text-align:right"><?= $cur ?> <?= number_format($amount, 2) ?></td></tr>
        <?php endforeach ?>
    </table>
    <?php if ($announcements->isNotEmpty()): ?>
    <h4>Últimos comunicados</h4>
    <ul>
        <?php foreach ($announcements as $a): ?>
        <li><a href="<?= Backend::url('aero/school/announcements/update/' . $a->id) ?>"><?= e($a->title) ?></a>
            <span class="text-muted">— <?= $a->status === 'sent' ? $a->delivered . ' entregados' : 'borrador' ?></span></li>
        <?php endforeach ?>
    </ul>
    <?php endif ?>
</div>
<div class="col-md-6">
    <h4>Puesta en marcha (<?= $done ?>/<?= count($steps) ?>)</h4>
    <ul class="sch-steps list-unstyled">
        <?php foreach ($steps as [$label, $ok, $path]): ?>
        <li><?= $ok ? '<span class="sch-ok">✔</span>' : '<span class="text-muted">○</span>' ?>
            <?php if ($path && !$ok): ?><a href="<?= Backend::url('aero/school/' . $path) ?>"><?= e($label) ?></a><?php else: ?><?= e($label) ?><?php endif ?>
        </li>
        <?php endforeach ?>
    </ul>
</div>
</div>
