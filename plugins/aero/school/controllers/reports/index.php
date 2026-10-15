<?php $tabs = ['morosidad' => 'Morosidad', 'cursos' => 'Ingresos por curso', 'ingresos' => 'Ingresos por mes']; ?>
<ul class="nav nav-tabs" style="margin-bottom:15px">
    <?php foreach ($tabs as $k => $label): ?>
        <li class="<?= $tab === $k ? 'active' : '' ?>"><a href="<?= Backend::url('aero/school/reports?r=' . $k) ?>"><?= e($label) ?></a></li>
    <?php endforeach ?>
</ul>
<p>
    <a class="btn btn-default btn-sm" href="<?= Backend::url('aero/school/reports?r=' . $tab . '&export=csv' . ($courseId ? '&course_id=' . $courseId : '')) ?>">Exportar CSV</a>
</p>

<?php if ($tab === 'morosidad'): ?>
    <form method="get" class="form-inline" style="margin-bottom:12px;display:inline-block">
        <input type="hidden" name="r" value="morosidad">
        <select name="course_id" class="form-control" onchange="this.form.submit()">
            <option value="">Todos los cursos</option>
            <?php foreach ($courses as $c): ?><option value="<?= $c->id ?>" <?= $courseId === $c->id ? 'selected' : '' ?>><?= e($c->name) ?></option><?php endforeach ?>
        </select>
    </form>
    <?= Form::open(['style' => 'display:inline-block']) ?>
        <input type="hidden" name="course_id" value="<?= (int) $courseId ?>">
        <button type="button" class="btn btn-warning" data-request="onRemindAll"
            data-request-confirm="¿Enviar recordatorio de mora por WhatsApp a todos los apoderados de esta lista?">Enviar recordatorio a todos</button>
    <?= Form::close() ?>
    <p>Deuda vencida: <?php foreach ($aging as $range => $v): ?><span class="label label-default"><?= e($range) ?> d: <?= number_format($v, 2) ?></span> <?php endforeach ?></p>
    <table class="table data">
        <thead><tr><th>Estudiante</th><th>Curso</th><th>Cuotas vencidas</th><th>Días de atraso</th><th style="text-align:right">Deuda</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($data as $r): ?>
            <tr><td><?= e($r['student']) ?></td><td><?= e($r['course']) ?></td><td><?= (int) $r['charges'] ?></td><td><?= (int) $r['days'] ?></td>
                <td style="text-align:right"><strong><?= number_format($r['due'], 2) ?></strong></td>
                <td><a class="btn btn-xs btn-primary" href="<?= Backend::url('aero/school/collect/student/' . $r['student_id']) ?>">Cobrar</a></td></tr>
        <?php endforeach ?>
        <?php if (!$data): ?><tr><td colspan="6" class="text-muted">Sin morosidad. 🎉</td></tr><?php endif ?>
        </tbody>
    </table>
<?php elseif ($tab === 'cursos'): ?>
    <table class="table data">
        <thead><tr><th>Curso</th><th>Estudiantes</th><th style="text-align:right">Facturado</th><th style="text-align:right">Cobrado</th>
            <th style="text-align:right">Por cobrar</th><th style="text-align:right">% cobrado</th></tr></thead>
        <tbody>
        <?php foreach ($data as $r): ?>
            <tr><td><?= e($r['course']) ?></td><td><?= (int) $r['students'] ?></td><td style="text-align:right"><?= number_format($r['billed'], 2) ?></td>
                <td style="text-align:right"><?= number_format($r['paid'], 2) ?></td><td style="text-align:right"><?= number_format($r['pending'], 2) ?></td>
                <td style="text-align:right"><?= $r['rate'] ?>%</td></tr>
        <?php endforeach ?>
        <?php if (!$data): ?><tr><td colspan="6" class="text-muted">Aún no hay cuotas generadas.</td></tr><?php endif ?>
        </tbody>
    </table>
<?php else: ?>
    <table class="table data" style="max-width:600px">
        <thead><tr><th>Mes</th><th>Pagos</th><th style="text-align:right">Ingresos</th></tr></thead>
        <tbody>
        <?php foreach ($data as $r): ?>
            <tr><td><?= e($r['month']) ?></td><td><?= (int) $r['payments'] ?></td><td style="text-align:right"><?= number_format($r['total'], 2) ?></td></tr>
        <?php endforeach ?>
        <?php if (!$data): ?><tr><td colspan="3" class="text-muted">Aún no hay pagos.</td></tr><?php endif ?>
        </tbody>
    </table>
<?php endif ?>
