<?php $r = $this->formGetModel(); if (!$r || !$r->exists) return; ?>
<div class="callout callout-info" style="margin-bottom:10px">
    <div class="content">
        <a href="<?= Backend::url('aero/gym/routines/printout/' . $r->id) ?>" target="_blank" class="btn btn-default oc-icon-print">Imprimir</a>
        <?php if (!$r->member_id): ?>
            <select id="gym-assign-member" class="form-control custom-select" style="display:inline-block;width:240px;margin-left:10px">
                <option value="">— Copiar a un socio —</option>
                <?php foreach (\Aero\Gym\Models\Member::visible()->orderBy('name')->get(['id', 'name']) as $m): ?><option value="<?= $m->id ?>"><?= e($m->name) ?></option><?php endforeach ?>
            </select>
            <button type="button" class="btn btn-primary" data-request="onAssign" data-request-data="member_id: document.getElementById('gym-assign-member').value">Asignar</button>
        <?php endif ?>
    </div>
</div>
