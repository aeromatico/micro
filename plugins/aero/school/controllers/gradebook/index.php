<form method="get" class="form-inline" style="margin-bottom:15px">
    <select name="course_id" class="form-control" onchange="this.form.subject_id.value='';this.form.submit()">
        <option value="">— Curso —</option>
        <?php foreach ($courses as $c): ?>
            <option value="<?= $c->id ?>" <?= $course && $course->id === $c->id ? 'selected' : '' ?>><?= e($c->name) ?></option>
        <?php endforeach ?>
    </select>
    <select name="subject_id" class="form-control" onchange="this.form.submit()">
        <option value="">— Área —</option>
        <?php foreach ($subjects as $s): ?>
            <option value="<?= $s->id ?>" <?= $subject && $subject->id === $s->id ? 'selected' : '' ?>><?= e($s->name) ?></option>
        <?php endforeach ?>
    </select>
    <select name="period" class="form-control" onchange="this.form.submit()">
        <?php for ($p = 1; $p <= $periods; $p++): ?>
            <option value="<?= $p ?>" <?= $period === $p ? 'selected' : '' ?>><?= e($periodNames[$p] ?? ('Periodo ' . $p)) ?></option>
        <?php endfor ?>
    </select>
    <button class="btn btn-default">Ver</button>
    <?php if ($course): ?>
        <a class="btn btn-default" target="_blank" href="<?= Backend::url('aero/school/gradebook/coursecards/' . $course->id) ?>">Boletines del curso (PDF)</a>
    <?php endif ?>
</form>

<?php if (!$course): ?>
    <p class="text-muted">Elija curso, área y trimestre para cargar las notas.</p>
<?php elseif ($subjects->isEmpty()): ?>
    <div class="callout callout-warning"><div class="header"><h3>Este curso no tiene áreas.</h3></div>
        <div class="content"><a class="btn btn-primary" href="<?= Backend::url('aero/school/courses/update/' . $course->id) ?>">Cargar áreas del currículo base</a></div></div>
<?php elseif (!$subject): ?>
    <p class="text-muted">Elija el área.</p>
<?php elseif ($students->isEmpty()): ?>
    <p class="text-muted">Este curso no tiene estudiantes inscritos.</p>
<?php else: ?>
<?= Form::open() ?>
    <input type="hidden" name="course_id" value="<?= $course->id ?>">
    <input type="hidden" name="subject_id" value="<?= $subject->id ?>">
    <input type="hidden" name="period" value="<?= $period ?>">
    <?php $q = $course->isQualitative(); ?>
    <table class="table data" id="grade-grid" style="max-width:1000px">
        <thead><tr><th>Estudiante</th>
        <?php if ($q): ?><th>Valoración cualitativa</th>
        <?php else: ?>
            <?php foreach ([['ser', 'Ser'], ['saber', 'Saber'], ['hacer', 'Hacer'], ['decidir', 'Decidir'], ['auto_ser', 'Auto Ser'], ['auto_decidir', 'Auto Dec.']] as [$k, $label]): ?>
                <th style="text-align:center;width:80px"><?= $label ?><br><small>/<?= $max[$k] ?></small></th>
            <?php endforeach ?>
            <th style="text-align:center;width:70px">Total<br><small>/100</small></th>
        <?php endif ?>
        </tr></thead>
        <tbody>
        <?php foreach ($students as $i => $s): $g = $grades[$s->id] ?? null; ?>
            <tr>
                <td><?= $i + 1 ?>. <?= e($s->full_name) ?></td>
                <?php if ($q): ?>
                    <td><input type="text" class="form-control input-sm" name="rows[<?= $s->id ?>][qualitative]" value="<?= e($g->qualitative ?? '') ?>" maxlength="255"></td>
                <?php else: ?>
                    <?php foreach (array_keys($max) as $k): ?>
                        <td><input type="number" step="0.5" min="0" max="<?= $max[$k] ?>" class="form-control input-sm g-in" data-max="<?= $max[$k] ?>"
                            name="rows[<?= $s->id ?>][<?= $k ?>]" value="<?= $g && $g->{$k} !== null ? e((float) $g->{$k}) : '' ?>"></td>
                    <?php endforeach ?>
                    <td style="text-align:center"><strong class="g-total"><?= $g && $g->total !== null ? (int) $g->total : '' ?></strong></td>
                <?php endif ?>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    <p>
        <button type="button" class="btn btn-primary" data-request="onSave" data-request-loading data-hotkey="ctrl+enter">Guardar notas</button>
        <span class="text-muted">Se calcula el total automáticamente (Ser 10 + Saber 35 + Hacer 35 + Decidir 10 + autoevaluación 10).</span>
    </p>
<?= Form::close() ?>
<script>
document.querySelectorAll('#grade-grid tbody tr').forEach(function (tr) {
    var inputs = tr.querySelectorAll('.g-in'), total = tr.querySelector('.g-total');
    if (!total) return;
    function calc() {
        var sum = 0, any = false;
        inputs.forEach(function (i) {
            var v = parseFloat(i.value);
            if (!isNaN(v)) { any = true; sum += v; }
            i.style.borderColor = (!isNaN(v) && (v < 0 || v > parseFloat(i.dataset.max))) ? '#c0392b' : '';
        });
        total.textContent = any ? Math.round(sum) : '';
    }
    inputs.forEach(function (i) { i.addEventListener('input', calc); });
});
</script>
<?php endif ?>
