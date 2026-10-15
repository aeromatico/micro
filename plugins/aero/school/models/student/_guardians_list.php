<?php
/** Apoderados ya vinculados al estudiante (solo al editar). */
$student = $formModel;
$guardians = $student && $student->exists ? $student->guardians()->get() : collect();
?>
<?php if ($student && $student->exists): ?>
<h4 style="margin-top:0">Apoderados vinculados (<?= $guardians->count() ?>)</h4>
<?php if ($guardians->isEmpty()): ?>
    <p class="text-muted">Este estudiante aún no tiene apoderado. Agregue uno abajo: sin apoderado no hay a quién avisar por WhatsApp.</p>
<?php else: ?>
<table class="table data" style="margin-bottom:20px">
    <thead><tr><th>Nombre</th><th>Parentesco</th><th>Celular (WhatsApp)</th><th>Correo</th><th>Recibe cobranza</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($guardians as $g): ?>
        <tr>
            <td><a href="<?= Backend::url('aero/school/guardians/update/' . $g->id) ?>"><strong><?= e($g->name) ?></strong></a>
                <?php if ($g->pivot->is_primary): ?><span class="label label-info">Principal</span><?php endif ?></td>
            <td><?= e($g->pivot->relationship ?: '—') ?></td>
            <td><?= $g->phone ? e($g->phone) : '<span class="text-danger">sin celular</span>' ?></td>
            <td><?= e($g->email ?: '—') ?></td>
            <td><?= $g->pivot->is_financial ? 'Sí' : '—' ?></td>
            <td style="text-align:right;white-space:nowrap">
                <a href="<?= Backend::url('aero/school/guardians/update/' . $g->id) ?>" class="btn btn-xs btn-default">Ver ficha</a>
                <a href="#" class="btn btn-xs btn-danger" data-request="onRemoveGuardian" data-request-data="guardian_id: <?= (int) $g->id ?>"
                   data-request-confirm="¿Desvincular a <?= e($g->name) ?> de este estudiante?">Desvincular</a>
            </td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>
<?php endif ?>
<?php else: ?>
<p class="text-muted">Guarde al estudiante para ver aquí sus apoderados.</p>
<?php endif ?>
