<?php
$q = \Aero\Tickets\Models\Scan::query();
if (!\Aero\Tickets\Classes\CurrentTenant::isAdmin()) { $id = \Aero\Tickets\Classes\CurrentTenant::id(); $id ? $q->where('tenant_id', $id) : $q->whereRaw('1=0'); }
?>
<div data-control="toolbar"><span class="text-muted">
    Ingresos registrados: <strong><?= (clone $q)->where('result', 'ok')->count() ?></strong> ·
    Rechazos: <strong><?= (clone $q)->where('result', '!=', 'ok')->count() ?></strong> ·
    Duplicados bloqueados: <strong><?= (clone $q)->where('result', 'already_used')->count() ?></strong>
    — filtra por evento, resultado u operador.
</span> <a href="<?= Backend::url('aero/tickets/checkin') ?>" class="btn btn-primary" style="margin-left:12px">Ir al control de ingreso</a></div>
