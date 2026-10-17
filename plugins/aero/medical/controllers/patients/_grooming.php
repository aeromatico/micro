<?php
$visits = \Aero\Medical\Models\GroomingVisit::where('tenant_id', $p->tenant_id)->where('patient_id', $p->id)->orderByDesc('visited_on')->orderByDesc('id')->limit(8)->get();
?>
<?php if (!count($visits)): ?><p class="text-muted" style="font-size:13px">Sin visitas de peluquería y spa.</p><?php else: ?>
<table class="table table-condensed" style="font-size:12.5px">
    <?php foreach ($visits as $v): ?>
    <tr><td style="width:90px"><?= e($v->visited_on->format('d/m/Y')) ?></td><td><?= e($v->service) ?></td>
        <td><?= $v->next_visit_on ? 'próxima ' . e($v->next_visit_on->format('d/m/Y')) : '' ?></td>
        <td class="text-right"><a href="<?= Backend::url('aero/medical/groomings/update/' . $v->id) ?>">abrir</a></td></tr>
    <?php endforeach ?>
</table>
<?php endif ?>
<a class="btn btn-default btn-xs" href="<?= Backend::url('aero/medical/groomings') ?>">Ir a Peluquería y spa</a>
