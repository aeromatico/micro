<?php /* Diagnósticos, receta y órdenes de la nota (se actualizan por AJAX sin recargar). */ ?>
<div class="mc-card">
    <h5>Diagnósticos </h5>
    <div id="mc-dx"><?= $this->makePartial('dx') ?></div>
    <?php if ($editable): ?>
        <input type="text" class="form-control" name="dx_q" placeholder="'Buscar por nombre o código CIE-10…'"
               autocomplete="off" data-request="onSearchDx" data-track-input="300" id="mc-dx-q">
        <div id="mc-dx-results" style="margin:6px 0"><?= $this->makePartial('dxresults', ['searched' => false]) ?></div>
        <?= Form::open(['data-request' => 'onAddDx', 'class' => 'mc-reset', 'style' => 'display:flex;gap:6px;margin-top:6px']) ?>
            <input type="hidden" name="source" value="free">
            <input type="text" class="form-control" name="label" placeholder="Otro diagnóstico (texto libre)" maxlength="500" required>
            <button type="submit" class="btn btn-default">Agregar</button>
        <?= Form::close() ?>
    <?php endif ?>
</div>

<div class="mc-card">
    <h5>Receta
        <?php if ($c->isSigned() && $rx && count($rx->items)): ?>
            <a class="btn btn-default btn-xs" style="float:right" target="_blank" href="<?= Backend::url('aero/clinica/consultations/printrx/' . $c->id) ?>">Imprimir receta</a>
        <?php endif ?></h5>
    <div id="mc-rx"><?= $this->makePartial('rx') ?></div>
    <?php if ($editable): ?>
        <input type="text" class="form-control" name="med_q" id="mc-med-q" autocomplete="off" data-request="onSearchMed" data-track-input="300"
               placeholder="Buscar entre los medicamentos que esta clínica ya recetó… (o escriba abajo)">
        <div id="mc-med-results" style="margin:6px 0"><?= $this->makePartial('medresults') ?></div>
        <?= Form::open(['data-request' => 'onAddRx', 'class' => 'mc-reset', 'id' => 'mc-rx-form']) ?>
            <div class="mc-vitals">
                <div style="grid-column:1/-1"><label>Medicamento</label><input type="text" class="form-control" name="medication" id="rx-medication" required maxlength="2000"></div>
                <div><label>Principio activo</label><input type="text" class="form-control" name="active_ingredient" id="rx-ingredient" maxlength="2000"></div>
                <div><label>Concentración</label><input type="text" class="form-control" name="strength" id="rx-strength" maxlength="2000" placeholder="500 mg"></div>
                <div><label>Presentación</label><input type="text" class="form-control" name="presentation" id="rx-presentation" maxlength="2000" placeholder="tabletas, jarabe…"></div>
                <div><label>Dosis</label><input type="text" class="form-control" name="dose" maxlength="2000"></div>
                <div><label>Vía</label><input type="text" class="form-control" name="route" maxlength="2000" placeholder="oral, IM, IV…"></div>
                <div><label>Frecuencia</label><input type="text" class="form-control" name="frequency" maxlength="2000"></div>
                <div><label>Duración</label><input type="text" class="form-control" name="duration" maxlength="2000"></div>
                <div><label>Cantidad</label><input type="text" class="form-control" name="quantity" maxlength="2000"></div>
                <div style="grid-column:1/-1"><label>Indicaciones</label><input type="text" class="form-control" name="instructions" maxlength="2000"></div>
            </div>
            <button type="submit" class="btn btn-default" style="margin-top:8px">Agregar a la receta</button>
        <?= Form::close() ?>
    <?php endif ?>
</div>

<div class="mc-card">
    <h5>Órdenes de exámenes
        <?php if ($c->isSigned() && $order && count($order->items)): ?>
            <a class="btn btn-default btn-xs" style="float:right" target="_blank" href="<?= Backend::url('aero/clinica/consultations/printorders/' . $c->id) ?>">Imprimir órdenes</a>
        <?php endif ?></h5>
    <div id="mc-orders"><?= $this->makePartial('orders') ?></div>
    <?php if ($editable): ?>
        <?= Form::open(['data-request' => 'onAddOrder', 'class' => 'mc-reset', 'style' => 'display:grid;grid-template-columns:1fr 1fr auto;gap:6px']) ?>
            <select name="catalog_id" class="form-control">
                <option value="">— Examen del catálogo —</option>
                <?php foreach ($exams as $ex): ?><option value="<?= (int) $ex->id ?>"><?= e($ex->name) ?></option><?php endforeach ?>
            </select>
            <input type="text" class="form-control" name="exam_name" placeholder="…o escriba otro examen" maxlength="2000">
            <button type="submit" class="btn btn-default">Agregar</button>
            <input type="text" class="form-control" name="exam_instructions" placeholder="Indicaciones (opcional)" maxlength="2000" style="grid-column:1/-1">
        <?= Form::close() ?>
    <?php endif ?>
</div>

<script>
(function () {
    // Limpia los mini-formularios tras agregar y deja elegir un medicamento ya recetado.
    document.addEventListener('ajax:done', function (e) {
        var f = e.target;
        if (f && f.classList && f.classList.contains('mc-reset')) {
            f.reset();
        }
    });
    // Tras agregar un diagnóstico o medicamento del buscador, se vacía el buscador.
    document.addEventListener('ajax:done', function (e) {
        var t = e.target, h = t && t.getAttribute && t.getAttribute('data-request');
        var clear = function (q, r) { var a = document.getElementById(q), b = document.getElementById(r); if (a) a.value = ''; if (b) b.innerHTML = ''; };
        if (h === 'onAddDx' && t.tagName === 'BUTTON') clear('mc-dx-q', 'mc-dx-results');
        if (h === 'onAddRx') clear('mc-med-q', 'mc-med-results');
    });
    document.addEventListener('click', function (e) {
        var b = e.target.closest && e.target.closest('.mc-pick');
        if (!b) return;
        document.getElementById('rx-medication').value = b.dataset.text;
        document.getElementById('rx-ingredient').value = b.dataset.ingredient || '';
        document.getElementById('rx-strength').value = b.dataset.strength || '';
        document.getElementById('rx-presentation').value = b.dataset.presentation || '';
    });
})();
</script>
