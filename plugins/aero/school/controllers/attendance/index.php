<form method="get" class="form-inline" style="margin-bottom:15px">
    <select name="course_id" class="form-control" onchange="this.form.submit()">
        <option value="">— Elija el curso —</option>
        <?php foreach ($courses as $c): ?>
            <option value="<?= $c->id ?>" <?= $course && $course->id === $c->id ? 'selected' : '' ?>><?= e($c->name) ?> (<?= e($c->level_label) ?>)</option>
        <?php endforeach ?>
    </select>
    <input type="date" name="date" value="<?= e($date) ?>" max="<?= date('Y-m-d') ?>" class="form-control" onchange="this.form.submit()">
    <button class="btn btn-default">Ver</button>
</form>

<?php if (!$course): ?>
    <p class="text-muted">Elija un curso para pasar lista.</p>
<?php elseif ($students->isEmpty()): ?>
    <p class="text-muted">Este curso no tiene estudiantes inscritos.</p>
<?php else: ?>
<?= Form::open() ?>
    <input type="hidden" name="course_id" value="<?= $course->id ?>">
    <input type="hidden" name="date" value="<?= e($date) ?>">
    <p>
        <button type="button" class="btn btn-sm btn-default" onclick="document.querySelectorAll('.att-present').forEach(function(r){r.checked=true})">Marcar todos presentes</button>
        <span class="text-muted"><?= $students->count() ?> estudiantes · <?= \Carbon\Carbon::parse($date)->translatedFormat('l d \d\e F') ?></span>
    </p>
    <table class="table data" style="max-width:900px">
        <thead><tr><th>Estudiante</th>
            <?php foreach ($statuses as $label): ?><th style="text-align:center"><?= e($label) ?></th><?php endforeach ?>
            <th>Observación</th></tr></thead>
        <tbody>
        <?php foreach ($students as $i => $s): $m = $marks[$s->id] ?? null; $cur = $m->status ?? 'present'; ?>
            <tr>
                <td><?= $i + 1 ?>. <?= e($s->full_name) ?></td>
                <?php foreach ($statuses as $key => $label): ?>
                    <td style="text-align:center"><input type="radio" name="rows[<?= $s->id ?>][status]" value="<?= $key ?>"
                        class="<?= $key === 'present' ? 'att-present' : '' ?>" <?= $cur === $key ? 'checked' : '' ?>></td>
                <?php endforeach ?>
                <td><input type="text" name="rows[<?= $s->id ?>][note]" value="<?= e($m->note ?? '') ?>" class="form-control input-sm" maxlength="200"></td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    <label><input type="checkbox" name="notify" value="1" checked> Avisar por WhatsApp a los apoderados de los ausentes</label>
    <p><button type="button" class="btn btn-primary" data-request="onSave" data-request-loading data-hotkey="ctrl+enter">Guardar asistencia</button></p>
<?= Form::close() ?>
<?php endif ?>
