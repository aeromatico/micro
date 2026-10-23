<?php
$rows = $model->group->activeMembers()->get();
$marked = $model->attendances()->pluck('present', 'member_id')->all();
$count = collect($marked)->filter()->count();
?>
<div class="form-group" style="margin-top:20px">
    <h4>Asistencia (<?= $count ?> de <?= $rows->count() ?>)</h4>
    <?php if ($rows->isEmpty()): ?>
        <p class="text-muted">El grupo no tiene integrantes. Agrégalos en Grupos para tomar asistencia.</p>
    <?php else: ?>
    <?= Form::open() ?>
        <table class="table data">
            <thead><tr><th style="width:80px">Presente</th><th>Integrante</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $gm): ?>
                <tr>
                    <td><input type="checkbox" name="present[]" value="<?= (int) $gm->member_id ?>" <?= !empty($marked[$gm->member_id]) ? 'checked' : '' ?>></td>
                    <td><?= e($gm->member?->full_name) ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
        <button type="button" class="btn btn-primary" data-request="onSaveAttendance">Guardar asistencia</button>
    <?= Form::close() ?>
    <?php endif ?>
</div>
