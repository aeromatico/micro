<?php
$rows = $model->activeMembers()->get();
$taken = $rows->pluck('member_id')->all();
$options = \Aero\Church\Models\Member::visible()->whereNotIn('id', $taken)->orderBy('first_name')->get();
?>
<div class="form-group" style="margin-top:20px">
    <h4>Integrantes (<?= $rows->count() ?>)</h4>
    <?php if ($rows->isEmpty()): ?>
        <p class="text-muted">Aún no hay integrantes.</p>
    <?php else: ?>
    <table class="table data">
        <thead><tr><th>Nombre</th><th>Rol</th><th>Desde</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $gm): ?>
            <tr>
                <td><?= e($gm->member?->full_name) ?></td>
                <td><?= e(\Aero\Church\Models\GroupMember::ROLES[$gm->role] ?? $gm->role) ?></td>
                <td><?= e($gm->joined_at?->format('d/m/Y')) ?></td>
                <td><button type="button" class="btn btn-sm btn-default" data-request="onRemoveMember" data-request-data="id: <?= (int) $gm->id ?>" data-request-confirm="¿Dar de baja a este integrante?">Quitar</button></td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    <?php endif ?>

    <?php if ($options->isNotEmpty()): ?>
    <div class="form-inline" style="margin-top:10px">
        <select name="member_id" class="form-control custom-select" style="min-width:220px">
            <option value="">— Elegir miembro —</option>
            <?php foreach ($options as $m): ?><option value="<?= (int) $m->id ?>"><?= e($m->full_name) ?></option><?php endforeach ?>
        </select>
        <select name="role" class="form-control custom-select">
            <?php foreach (\Aero\Church\Models\GroupMember::ROLES as $k => $v): ?><option value="<?= $k ?>" <?= $k === 'member' ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach ?>
        </select>
        <button type="button" class="btn btn-primary" data-request="onAddMember" data-request-data="" data-request-form="">Agregar</button>
    </div>
    <?php endif ?>
</div>
