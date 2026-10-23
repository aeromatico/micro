<?php
$regs = $model->registrations()->where('status', '!=', 'cancelled')->with('member')->orderByRaw("status = 'waitlist'")->orderBy('id')->get();
$taken = $regs->pluck('member_id')->filter()->all();
$options = \Aero\Church\Models\Member::visible()->whereNotIn('id', $taken)->orderBy('first_name')->get();
$statusLabel = ['registered' => 'Inscrito', 'waitlist' => 'En espera'];
?>
<div class="form-group" style="margin-top:20px">
    <h4>
        Inscritos: <?= $model->seated ?><?= $model->capacity ? ' de ' . (int) $model->capacity : '' ?>
        · Llegaron: <?= $model->attended ?>
        <?php if ($regs->where('status', 'waitlist')->count()): ?>· En espera: <?= $regs->where('status', 'waitlist')->count() ?><?php endif ?>
    </h4>

    <?= Form::open() ?>
    <div class="form-inline" style="margin:10px 0">
        <input type="text" name="token" class="form-control" placeholder="Escanear código QR…" autocomplete="off" style="min-width:240px"
               onkeydown="if(event.key==='Enter'){event.preventDefault();$(this).request('onCheckInToken');}">
        <button type="button" class="btn btn-default" data-request="onCheckInToken">Marcar llegada por código</button>
    </div>
    <?= Form::close() ?>

    <?php if ($regs->isEmpty()): ?>
        <p class="text-muted">Aún no hay inscritos.</p>
    <?php else: ?>
    <table class="table data">
        <thead><tr><th>Nombre</th><th>Estado</th><th>Llegada</th><th>QR</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($regs as $r): ?>
            <tr>
                <td><?= e($r->display_name) ?><?= $r->member ? '' : ' <span class="text-muted">(invitado)</span>' ?></td>
                <td><?= e($statusLabel[$r->status] ?? $r->status) ?></td>
                <td><?= $r->checked_in_at ? e($r->checked_in_at->format('H:i')) : '—' ?></td>
                <td style="width:90px"><div style="width:72px;height:72px"><?= $r->status === 'registered' ? str_replace('<svg ', '<svg width="72" height="72" ', $r->qrSvg(72)) : '' ?></div></td>
                <td class="text-right">
                    <?php if ($r->status === 'registered'): ?>
                        <?php if ($r->checked_in_at): ?>
                            <button type="button" class="btn btn-sm btn-default" data-request="onUndoCheckIn" data-request-data="id: <?= (int) $r->id ?>">Deshacer llegada</button>
                        <?php else: ?>
                            <button type="button" class="btn btn-sm btn-success" data-request="onCheckIn" data-request-data="id: <?= (int) $r->id ?>">Marcar llegada</button>
                        <?php endif ?>
                    <?php endif ?>
                    <button type="button" class="btn btn-sm btn-default" data-request="onCancelRegistration" data-request-data="id: <?= (int) $r->id ?>" data-request-confirm="¿Cancelar esta inscripción?">Cancelar</button>
                </td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    <?php endif ?>

    <?= Form::open() ?>
    <div class="form-inline" style="margin-top:10px">
        <select name="member_id" class="form-control custom-select" style="min-width:220px">
            <option value="">— Elegir miembro —</option>
            <?php foreach ($options as $m): ?><option value="<?= (int) $m->id ?>"><?= e($m->full_name) ?></option><?php endforeach ?>
        </select>
        <button type="button" class="btn btn-primary" data-request="onRegisterMember">Inscribir</button>
        <button type="button" class="btn btn-default" data-request="onWalkIn" data-request-confirm="¿Registrar su llegada ahora (sin inscripción previa)?">Llegó sin inscribirse</button>
    </div>
    <div class="form-inline" style="margin-top:10px">
        <input type="text" name="guest_name" class="form-control" placeholder="Nombre del invitado">
        <input type="text" name="guest_phone" class="form-control" placeholder="Teléfono (opcional)">
        <button type="button" class="btn btn-primary" data-request="onRegisterGuest">Inscribir invitado</button>
        <button type="button" class="btn btn-default" data-request="onWalkIn">Invitado llegó</button>
    </div>
    <?= Form::close() ?>
</div>
