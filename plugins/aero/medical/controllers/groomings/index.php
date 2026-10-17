<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/medical/groomings') ?>">Peluquería y spa</a></li></ul>
<?php Block::endPut() ?>
<div class="form-preview" style="max-width:900px;margin-bottom:22px">
    <h4 style="margin-top:0">Nueva visita</h4>
    <?= Form::open(['data-request' => 'onCreate', 'style' => 'display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:8px;align-items:end']) ?>
        <div><label style="font-size:12px">Paciente (animal)</label>
            <select name="patient_id" class="form-control" required><option value="">— Elegir —</option>
                <?php foreach ($patients as $p): ?><option value="<?= (int) $p->id ?>"><?= e($p->name) ?><?= $p->species ? ' (' . e(\Aero\Medical\Classes\Mode::species()[$p->species] ?? $p->species) . ')' : '' ?></option><?php endforeach ?></select></div>
        <div><label style="font-size:12px">Servicio</label><input type="text" name="service" class="form-control" required maxlength="255" placeholder="Baño, corte, spa…"></div>
        <div><label style="font-size:12px">Fecha</label><input type="date" name="visited_on" class="form-control" value="<?= e(now()->toDateString()) ?>" max="<?= e(now()->toDateString()) ?>"></div>
        <div><label style="font-size:12px">Próxima cita (opcional)</label><input type="date" name="next_visit_on" class="form-control"></div>
        <div style="grid-column:1/-1"><label style="font-size:12px">Notas</label><input type="text" name="notes" class="form-control" maxlength="2000"></div>
        <div><button type="submit" class="btn btn-primary">Crear y agregar fotos</button></div>
    <?= Form::close() ?>
    <?php if (!count($patients)): ?><p class="text-muted" style="margin:8px 0 0">Aún no hay pacientes animales activos.</p><?php endif ?>
</div>

<form method="get" style="margin-bottom:10px;max-width:420px"><input type="text" name="q" value="<?= e($q) ?>" class="form-control" placeholder="Buscar por nombre del animal…"></form>
<?php if (!count($visits)): ?><p class="text-muted">No hay visitas.</p><?php else: ?>
<table class="table data" style="max-width:900px">
    <thead><tr><th>Fecha</th><th>Paciente</th><th>Servicio</th><th>Próxima cita</th><th>Fotos</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($visits as $v): ?>
        <tr>
            <td><?= e($v->visited_on->format('d/m/Y')) ?></td><td><?= e($v->patient?->name) ?></td><td><?= e($v->service) ?></td>
            <td><?= $v->next_visit_on ? e($v->next_visit_on->format('d/m/Y')) : '<span class="text-muted">—</span>' ?></td>
            <td><?= (int) ($photoCounts[$v->id] ?? 0) ?></td>
            <td class="text-right"><a href="<?= Backend::url('aero/medical/groomings/update/' . $v->id) ?>">abrir</a></td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>
<?php endif ?>
