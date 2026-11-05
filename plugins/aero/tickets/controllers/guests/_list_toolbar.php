<?php
$q = \Aero\Tickets\Models\Guest::query();
if (!\Aero\Tickets\Classes\CurrentTenant::isAdmin()) { $id = \Aero\Tickets\Classes\CurrentTenant::id(); $id ? $q->where('tenant_id', $id) : $q->whereRaw('1=0'); }
$st = (clone $q)->where('status', 'active')->selectRaw("rsvp, COUNT(*) c, SUM(confirmed_count) people")->groupBy('rsvp')->get()->keyBy('rsvp');
$revoked = (clone $q)->where('status', 'revoked')->count();
?>
<div data-control="toolbar">
    <a href="<?= Backend::url('aero/tickets/guests/create') ?>" class="btn btn-primary oc-icon-plus">Nuevo invitado</a>
    <button class="btn btn-default oc-icon-paper-plane" disabled="disabled" data-list-checked-trigger data-request="onMarkSentChecked"
            data-request-confirm="¿Marcar como enviadas las invitaciones seleccionadas?" data-list-checked-request>Marcar enviadas</button>
    <button class="btn btn-default oc-icon-ban" disabled="disabled" data-list-checked-trigger data-request="onRevokeChecked"
            data-request-confirm="¿Revocar las invitaciones seleccionadas? Sus entradas sin usar se anulan." data-list-checked-request>Revocar</button>
    <span class="text-muted" style="margin-left:14px">
        Sin respuesta: <strong><?= (int) ($st['pending']->c ?? 0) ?></strong> ·
        Confirmaron: <strong><?= (int) ($st['confirmed']->c ?? 0) ?></strong> (<?= (int) ($st['confirmed']->people ?? 0) ?> personas) ·
        Rechazaron: <strong><?= (int) ($st['declined']->c ?? 0) ?></strong> ·
        Revocadas: <strong><?= $revoked ?></strong>
    </span>
</div>
