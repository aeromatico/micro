<?php if (!$year): ?>
    <div class="callout callout-warning"><div class="content">
        Primero cree la gestión del año escolar en <a href="<?= Backend::url('aero/school/years') ?>">Gestiones</a>.
    </div></div>
<?php else: ?>
<p class="text-muted" style="max-width:760px">
    Escriba cuánto cobra su colegio por nivel en la gestión <strong><?= e($year->name) ?></strong>.
    Al guardar, el sistema crea automáticamente la matrícula y las pensiones de cada estudiante inscrito
    (<strong><?= (int) $students ?></strong> hoy) y de los que se inscriban después. Deje vacío lo que no cobre.
</p>
<?= Form::open() ?>
    <table class="table data" style="max-width:640px">
        <thead><tr><th>Nivel</th><th>Matrícula (Bs)</th><th>Pensión mensual (Bs)</th></tr></thead>
        <tbody>
        <?php foreach (\Aero\School\Models\Course::LEVELS as $level => $label): ?>
            <tr>
                <td><strong><?= e($label) ?></strong></td>
                <td><input type="number" step="0.01" min="0" name="prices[<?= $level ?>][matricula]" value="<?= e($prices[$level]['matricula']) ?>" class="form-control" placeholder="0.00"></td>
                <td><input type="number" step="0.01" min="0" name="prices[<?= $level ?>][pension]" value="<?= e($prices[$level]['pension']) ?>" class="form-control" placeholder="0.00"></td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>

    <h4>¿Cómo se cobra la pensión?</h4>
    <div class="form-inline" style="margin-bottom:15px">
        <label>Cuotas</label>
        <input type="number" min="1" max="12" name="installments" value="<?= (int) $installments ?>" class="form-control" style="width:80px">
        <label style="margin-left:12px">desde</label>
        <select name="first_month" class="form-control">
            <?php foreach ($months as $n => $m): ?><option value="<?= $n ?>" <?= (int) $firstMonth === $n ? 'selected' : '' ?>><?= e($m) ?></option><?php endforeach ?>
        </select>
        <label style="margin-left:12px">vence el día</label>
        <input type="number" min="1" max="28" name="due_day" value="<?= (int) $dueDay ?>" class="form-control" style="width:80px">
        <span class="text-muted" style="margin-left:8px">de cada mes (la matrícula es una sola cuota)</span>
    </div>

    <p><button type="button" class="btn btn-primary" data-request="onSave" data-request-loading data-hotkey="ctrl+enter">Guardar precios y generar cuotas</button></p>
<?= Form::close() ?>

<p class="text-muted" style="max-width:760px">
    ¿Un curso o un concepto distinto (uniforme, excursión, un curso con otro precio)?
    <a href="<?= Backend::url('aero/school/feeplans') ?>">Otros cobros y excepciones</a><?= $exceptions ? ' (' . (int) $exceptions . ' configurados)' : '' ?>.
    Las becas y descuentos se ponen en la ficha de cada estudiante.
</p>
<?php endif ?>
