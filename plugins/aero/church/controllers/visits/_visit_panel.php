<?php
$contacts = $model->contacts()->orderByDesc('id')->get();
$channels = \Aero\Church\Models\VisitContact::CHANNELS;
?>
<div class="form-group" style="margin-top:20px">
    <?php if ($model->member_id): ?>
        <p class="text-success">Integrado como miembro (#<?= (int) $model->member_id ?>).</p>
    <?php elseif ($model->status !== 'closed'): ?>
        <button type="button" class="btn btn-success" data-request="onIntegrate" data-request-confirm="¿Registrarlo como miembro nuevo?">Integrar como miembro</button>
    <?php endif ?>

    <h4 style="margin-top:15px">Seguimiento (<?= $contacts->count() ?>)</h4>
    <?php if ($contacts->isEmpty()): ?>
        <p class="text-muted">Aún no se le ha contactado.</p>
    <?php else: ?>
    <table class="table data">
        <thead><tr><th>Fecha</th><th>Medio</th><th>Nota</th><th>Próxima acción</th></tr></thead>
        <tbody>
        <?php foreach ($contacts as $c): ?>
            <tr>
                <td><?= e($c->created_at->format('d/m/Y H:i')) ?></td>
                <td><?= e($channels[$c->channel] ?? $c->channel) ?></td>
                <td><?= e($c->note) ?></td>
                <td><?= e($c->next_action) ?><?= $c->next_action_date ? ' (' . e($c->next_action_date->format('d/m/Y')) . ')' : '' ?></td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    <?php endif ?>

    <?= Form::open() ?>
    <div class="form-inline" style="margin-top:10px">
        <select name="channel" class="form-control custom-select">
            <?php foreach ($channels as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach ?>
        </select>
        <input type="text" name="note" class="form-control" placeholder="¿Qué se habló?" style="min-width:260px">
        <input type="text" name="next_action" class="form-control" placeholder="Próxima acción">
        <input type="date" name="next_action_date" class="form-control">
        <button type="button" class="btn btn-primary" data-request="onAddContact">Registrar contacto</button>
    </div>
    <?= Form::close() ?>
</div>
