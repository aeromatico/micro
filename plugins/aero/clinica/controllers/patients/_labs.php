<?php
use Aero\Clinica\Models\LabResult;
use Aero\Clinica\Models\Order;
use Aero\Clinica\Models\OrderItem;

$labs = LabResult::where('tenant_id', $p->tenant_id)->where('patient_id', $p->id)->orderByDesc('measured_on')->orderByDesc('id')->limit(15)->get();
$pending = OrderItem::where('tenant_id', $p->tenant_id)->where('status', 'requested')
    ->whereIn('order_id', Order::where('tenant_id', $p->tenant_id)->where('patient_id', $p->id)->pluck('id'))->orderBy('id')->get();
?>
<?php if (count($pending)): ?>
<h5 style="margin:0 0 6px;font-size:12px;text-transform:uppercase;opacity:.7">Solicitudes pendientes de resultado</h5>
<table class="table table-condensed" style="font-size:12.5px;margin-bottom:10px">
    <?php foreach ($pending as $it): ?>
    <tr>
        <td><?= e($it->exam_name) ?> <small class="text-muted">· <?= e(\Aero\Clinica\Models\ExamCatalog::CATEGORIES[$it->category] ?? $it->category) ?></small></td>
        <td class="text-right"><?php if ($it->category === 'lab'): ?><button type="button" class="btn btn-default btn-xs" data-fill-lab
            data-item="<?= (int) $it->id ?>" data-name="<?= e($it->exam_name) ?>">Registrar resultado</button><?php endif ?></td>
    </tr>
    <?php endforeach ?>
</table>
<?php endif ?>
<h5 style="margin:0 0 6px;font-size:12px;text-transform:uppercase;opacity:.7">Resultados</h5>
<?php if (count($labs)): ?>
<table class="table table-condensed" style="font-size:12.5px;margin:0">
    <tr class="text-muted"><td>Fecha</td><td>Análisis</td><td>Resultado</td><td>Referencia</td><td></td></tr>
    <?php foreach ($labs as $l): $flag = $l->flag($p->sex); ?>
    <tr>
        <td style="width:90px"><?= e($l->measured_on->format('d/m/Y')) ?></td>
        <td><?= e($l->analyte_name) ?><?= $l->lab_name ? ' <small class="text-muted">· ' . e($l->lab_name) . '</small>' : '' ?></td>
        <td class="<?= $flag ? 'mf-out' : '' ?>"><strong><?= e(rtrim(rtrim(number_format((float) $l->value, 3, '.', ''), '0'), '.')) ?></strong> <?= e($l->unit) ?><?= $flag === 'high' ? ' ▲' : ($flag === 'low' ? ' ▼' : '') ?></td>
        <td class="text-muted"><?= e($l->referenceText() ?: '—') ?></td>
        <td class="text-right"><button type="button" class="btn btn-danger btn-xs" data-request="onDeleteLab" data-request-data="lab_id: <?= (int) $l->id ?>"
                data-request-confirm="¿Eliminar este resultado?">Quitar</button></td>
    </tr>
    <?php endforeach ?>
</table>
<?php else: ?><p class="text-muted" style="margin:0;font-size:12px">Sin resultados anotados.</p><?php endif ?>
