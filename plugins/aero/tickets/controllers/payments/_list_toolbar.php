<?php
$q = \Aero\Tickets\Models\Payment::query();
if (!\Aero\Tickets\Classes\CurrentTenant::isAdmin()) { $id = \Aero\Tickets\Classes\CurrentTenant::id(); $id ? $q->where('tenant_id', $id) : $q->whereRaw('1=0'); }
$c = fn ($f) => (clone $q)->where($f)->count();
?>
<div data-control="toolbar">
    <span class="text-muted">
        Por verificar: <strong><?= $c(['status' => 'submitted']) ?></strong> ·
        Reembolsos pendientes: <strong><?= $c(['status' => 'refund_pending']) ?></strong> ·
        Sospechosos: <strong><?= $c(['is_suspicious' => 1]) ?></strong> ·
        Tardíos: <strong><?= $c(['is_late' => 1]) ?></strong>
        — Filtra por estado para trabajar la cola.
    </span>
</div>
