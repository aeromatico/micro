<?php
use Aero\Gym\Models\Booking;
$s = $this->formGetModel();
if (!$s || !$s->exists) return;
$rows = Booking::with('member')->where('session_id', $s->id)->whereIn('status', ['booked', 'attended', 'waitlist', 'no_show'])->orderBy('status')->orderBy('booked_at')->get();
$members = \Aero\Gym\Models\Member::visible()->where('status', 'active')->orderBy('name')->get(['id', 'name']);
?>
<div id="gym-roster" class="callout callout-info" style="margin-bottom:10px">
    <div class="content">
        <b>Cupo:</b> <?= $s->bookedCount() ?>/<?= (int) $s->capacity ?> · <b>Lista de espera:</b> <?= $s->waitlistCount() ?>
        <?php if ($s->status === 'scheduled'): ?>
            <button type="button" class="btn btn-danger btn-xs pull-right oc-icon-ban" data-request="onCancelSession" data-request-confirm="¿Cancelar la clase y avisar a los inscritos?">Cancelar clase</button>
        <?php endif ?>
        <?php if ($s->status === 'scheduled'): ?>
        <div style="margin:10px 0">
            <select id="gym-book-member" class="form-control custom-select" style="display:inline-block;width:260px">
                <option value="">— Inscribir socio —</option>
                <?php foreach ($members as $m): ?><option value="<?= $m->id ?>"><?= e($m->name) ?></option><?php endforeach ?>
            </select>
            <button type="button" class="btn btn-primary oc-icon-plus" data-request="onBook" data-request-data="member_id: document.getElementById('gym-book-member').value">Inscribir</button>
        </div>
        <?php endif ?>
        <table class="table data" style="margin-top:8px">
            <thead><tr><th>Socio</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $b): ?>
                <tr>
                    <td><?= e($b->member?->name) ?></td>
                    <td><?= e($b->getStatusOptions()[$b->status] ?? $b->status) ?></td>
                    <td class="text-right">
                        <?php if ($b->status === 'booked'): ?>
                            <button type="button" class="btn btn-success btn-xs" data-request="onCheckIn" data-request-data="booking_id: <?= $b->id ?>">Asistió</button>
                            <button type="button" class="btn btn-default btn-xs" data-request="onNoShow" data-request-data="booking_id: <?= $b->id ?>">No vino</button>
                        <?php endif ?>
                        <?php if (in_array($b->status, ['booked', 'waitlist'], true)): ?>
                            <button type="button" class="btn btn-danger btn-xs" data-request="onCancelBooking" data-request-data="booking_id: <?= $b->id ?>">Quitar</button>
                        <?php endif ?>
                    </td>
                </tr>
            <?php endforeach ?>
            <?php if ($rows->isEmpty()): ?><tr><td colspan="3" class="text-muted">Sin inscritos todavía.</td></tr><?php endif ?>
            </tbody>
        </table>
    </div>
</div>
