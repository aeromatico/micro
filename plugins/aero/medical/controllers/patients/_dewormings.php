<?php
use Aero\Medical\Classes\DewormingService;
use Aero\Medical\Models\Deworming;
use Aero\Medical\Models\Medication;

$cal = (new DewormingService())->calendar($p);
$ctl = $p->customer_id && \Aero\Medical\Classes\Scheduling::available();
$products = Medication::forTenant((int) $p->tenant_id)->where('is_active', true)->orderBy('name')->limit(80)->pluck('name');
$interval = (int) \Aero\Medical\Models\ClinicSettings::where('tenant_id', $p->tenant_id)->value('deworming_interval_days');
?>
<?php if (count($cal['due'])): ?>
    <h5 style="margin:0 0 6px;font-size:12px;text-transform:uppercase;opacity:.7">Próximas</h5>
    <table class="table table-condensed" style="margin-bottom:10px">
    <?php foreach ($cal['due'] as $d): $w = $d['deworming']; ?>
        <tr><td><strong><?= e($w->product) ?></strong> <small class="text-muted">tras <?= e($w->applied_on->format('d/m/Y')) ?></small></td>
            <td><?= e($d['due_on']->format('d/m/Y')) ?> <span class="mf-badge <?= $d['overdue'] ? 'over' : '' ?>"><?= $d['overdue'] ? 'vencida' : 'pendiente' ?></span></td>
            <td class="text-right"><?php if ($ctl): ?><button type="button" class="btn btn-default btn-xs" data-fill-book
                data-date="<?= e($d['due_on']->isPast() ? now()->addDay()->toDateString() : $d['due_on']->toDateString()) ?>" data-reason="<?= e('Desparasitación: ' . $w->product) ?>">Agendar</button><?php endif ?></td></tr>
    <?php endforeach ?>
    </table>
<?php endif ?>
<h5 style="margin:6px 0;font-size:12px;text-transform:uppercase;opacity:.7">Aplicadas</h5>
<?php if (!count($cal['applied'])): ?><p class="text-muted" style="font-size:13px">Sin desparasitaciones registradas.</p><?php else: ?>
<table class="table table-condensed" style="font-size:12.5px">
    <?php foreach ($cal['applied'] as $w): ?>
    <tr><td style="width:90px"><?= e($w->applied_on->format('d/m/Y')) ?></td>
        <td><?= e($w->product) ?><?= $w->dose ? ' · ' . e($w->dose) : '' ?><?= $w->kind ? ' <span class="mf-badge">' . e($w->kind_label) . '</span>' : '' ?><?= $w->lot ? ' <small class="text-muted">lote ' . e($w->lot) . '</small>' : '' ?></td>
        <td><?= $w->next_due_on ? 'próxima ' . e($w->next_due_on->format('d/m/Y')) : '' ?></td>
        <td class="text-right"><button type="button" class="btn btn-danger btn-xs" data-request="onDeleteDeworming" data-request-data="deworming_id: <?= (int) $w->id ?>" data-request-confirm="¿Eliminar este registro?">Quitar</button></td></tr>
    <?php endforeach ?>
</table>
<?php endif ?>
<?= Form::open(['data-request' => 'onApplyDeworming', 'class' => 'mc-reset', 'style' => 'display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px;align-items:end;margin-top:8px']) ?>
    <div><label style="font-size:12px">Producto</label><input type="text" name="product" class="form-control" required maxlength="255" list="mf-dw-products">
        <datalist id="mf-dw-products"><?php foreach ($products as $n): ?><option value="<?= e($n) ?>"><?php endforeach ?></datalist></div>
    <div><label style="font-size:12px">Tipo</label><select name="kind" class="form-control"><option value="">—</option><?php foreach (Deworming::KINDS as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach ?></select></div>
    <div><label style="font-size:12px">Dosis</label><input type="text" name="dose" class="form-control" maxlength="100"></div>
    <div><label style="font-size:12px">Fecha</label><input type="date" name="applied_on" class="form-control" value="<?= e(now()->toDateString()) ?>" max="<?= e(now()->toDateString()) ?>"></div>
    <div><label style="font-size:12px">Próxima<?= $interval ? ' (vacío = +' . $interval . ' d)' : '' ?></label><input type="date" name="next_due_on" class="form-control"></div>
    <div><label style="font-size:12px">Lote</label><input type="text" name="lot" class="form-control" maxlength="64"></div>
    <div><button type="submit" class="btn btn-default">Registrar</button></div>
<?= Form::close() ?>
<?php if (!$interval): ?><p class="text-muted" style="font-size:12px;margin:6px 0 0">Sin intervalo habitual definido (Configuración): la próxima fecha se indica a mano.</p><?php endif ?>
