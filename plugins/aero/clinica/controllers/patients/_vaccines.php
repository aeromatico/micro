<?php
use Aero\Clinica\Classes\VaccineService;
use Aero\Clinica\Models\Vaccine;

$cal = (new VaccineService())->calendar($p);
$catalog = Vaccine::forTenant((int) $p->tenant_id)->where('is_active', true)->orderBy('name')->get();
$ctl = $p->customer_id && \Aero\Clinica\Classes\Scheduling::available();
?>
<?php if (count($cal['due'])): ?>
    <h5 style="margin:0 0 6px;font-size:12px;text-transform:uppercase;opacity:.7">Próximas dosis</h5>
    <table class="table table-condensed" style="margin-bottom:10px">
    <?php foreach ($cal['due'] as $d): $v = $d['vaccination']; ?>
        <tr>
            <td><strong><?= e($v->vaccine_name) ?></strong> <small class="text-muted">tras <?= e($v->dose_label ?: 'la última dosis') ?></small></td>
            <td><?= e($d['due_on']->format('d/m/Y')) ?> <span class="mf-badge <?= $d['overdue'] ? 'over' : '' ?>"><?= $d['overdue'] ? 'vencida' : 'pendiente' ?></span></td>
            <td class="text-right"><?php if ($ctl): ?><button type="button" class="btn btn-default btn-xs" data-fill-book
                data-date="<?= e($d['due_on']->isPast() ? now()->addDay()->toDateString() : $d['due_on']->toDateString()) ?>"
                data-reason="<?= e('Control de vacunación') ?>">Agendar</button><?php endif ?></td>
        </tr>
    <?php endforeach ?>
    </table>
<?php endif ?>
<?php if (count($cal['not_started'])): ?>
    <p class="text-muted" style="font-size:12px">Sin iniciar: <?= e($cal['not_started']->pluck('name')->implode(', ')) ?></p>
<?php endif ?>

<h5 style="margin:6px 0;font-size:12px;text-transform:uppercase;opacity:.7">Historial de vacunación</h5>
<?php if (!count($cal['applied'])): ?><p class="text-muted" style="font-size:13px">Sin dosis registradas.</p><?php else: ?>
<table class="table table-condensed" style="font-size:12.5px">
    <?php foreach ($cal['applied'] as $v): ?>
    <tr>
        <td style="width:90px"><?= e($v->applied_on->format('d/m/Y')) ?></td>
        <td><?= e($v->vaccine_name) ?><?= $v->dose_label ? ' — ' . e($v->dose_label) : '' ?><?= $v->lot || $v->manufacturer || $v->route_site ? ' <small class="text-muted">' . e(implode(' · ', array_filter([$v->lot ? 'lote ' . $v->lot : null, $v->manufacturer, $v->route_site]))) . '</small>' : '' ?></td>
        <td><?= $v->next_due_on ? 'próxima ' . e($v->next_due_on->format('d/m/Y')) : '' ?></td>
        <td class="text-right"><button type="button" class="btn btn-danger btn-xs" data-request="onDeleteVaccination" data-request-data="vaccination_id: <?= (int) $v->id ?>" data-request-confirm="¿Eliminar este registro de vacuna?">Quitar</button></td>
    </tr>
    <?php endforeach ?>
</table>
<?php endif ?>

<?= Form::open(['data-request' => 'onApplyVaccine', 'class' => 'mc-reset', 'style' => 'display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px;align-items:end;margin-top:8px']) ?>
    <div><label style="font-size:12px">Vacuna del catálogo</label>
        <select name="vaccine_id" class="form-control"><option value="">— Otra (escribir) —</option>
            <?php foreach ($catalog as $v): ?><option value="<?= (int) $v->id ?>"><?= e($v->name) ?></option><?php endforeach ?></select></div>
    <div><label style="font-size:12px">…o nombre</label><input type="text" name="vaccine_name" class="form-control" maxlength="255"></div>
    <div><label style="font-size:12px">Dosis (opcional)</label><input type="text" name="dose_label" class="form-control" maxlength="255" placeholder="la sigue el esquema"></div>
    <div><label style="font-size:12px">Fecha</label><input type="date" name="applied_on" class="form-control" value="<?= e(now()->toDateString()) ?>" max="<?= e(now()->toDateString()) ?>"></div>
    <div><label style="font-size:12px">Lote</label><input type="text" name="lot" class="form-control" maxlength="64"></div>
    <div><label style="font-size:12px">Fabricante</label><input type="text" name="manufacturer" class="form-control" maxlength="128"></div>
    <div><label style="font-size:12px">Vía y sitio</label><input type="text" name="route_site" class="form-control" maxlength="64" placeholder="IM, deltoides izq."></div>
    <div><label style="font-size:12px">Próxima dosis (opcional)</label><input type="date" name="next_due_on" class="form-control"></div>
    <div><button type="submit" class="btn btn-default">Registrar dosis</button></div>
<?= Form::close() ?>
<?php if (!count($catalog)): ?><p class="text-muted" style="font-size:12px;margin:6px 0 0">Sin vacunas en el catálogo del negocio: se calcula la próxima dosis solo si la indica a mano. Defina el esquema en «Vacunas».</p><?php endif ?>
