<?php
$labs = \Aero\Medical\Models\LabResult::where('tenant_id', $p->tenant_id)->where('patient_id', $p->id)->orderByDesc('measured_on')->orderByDesc('id')->limit(12)->get();
?>
<?php if (count($labs)): ?>
<table class="table table-condensed" style="font-size:12.5px;margin:0">
    <?php foreach ($labs as $l): ?>
    <tr>
        <td style="width:90px"><?= e($l->measured_on->format('d/m/Y')) ?></td>
        <td><?= e($l->analyte_name) ?></td>
        <td><strong><?= e(rtrim(rtrim(number_format((float) $l->value, 3, '.', ''), '0'), '.')) ?></strong> <?= e($l->unit) ?></td>
        <td class="text-right"><button type="button" class="btn btn-danger btn-xs" data-request="onDeleteLab" data-request-data="lab_id: <?= (int) $l->id ?>"
                data-request-confirm="¿Eliminar este resultado?">Quitar</button></td>
    </tr>
    <?php endforeach ?>
</table>
<?php else: ?><p class="text-muted" style="margin:0;font-size:12px">Sin resultados anotados.</p><?php endif ?>
