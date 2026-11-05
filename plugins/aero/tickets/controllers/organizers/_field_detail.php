<?php
use Aero\Tickets\Classes\Currency;
use Aero\Tickets\Models\{Audit, Event, Order, CommissionEntry};
$o = $formModel;
$events = Event::where('tenant_id', $o->tenant_id)->orderByDesc('starts_at')->limit(50)->get();
$hist = Audit::where(fn ($q) => $q->where(fn ($w) => $w->where('subject_type', 'organizer')->where('subject_id', $o->id))->orWhere(fn ($w) => $w->where('tenant_id', $o->tenant_id)->where('subject_type', 'organizer')))->orderByDesc('id')->limit(100)->get();
$sales = (float) Order::where('tenant_id', $o->tenant_id)->where('status', 'confirmed')->sum('total');
$comm = (float) CommissionEntry::where('tenant_id', $o->tenant_id)->where('status', 'accrued')->sum('commission_amount');
$cls = ['approved' => 'success', 'pending' => 'warning', 'rejected' => 'danger', 'suspended' => 'danger'][$o->status] ?? 'info';
?>
<div class="callout callout-<?= $cls ?>"><div class="content">
    <p><strong><?= e($o->tenant_name ?: 'Espacio #' . $o->tenant_id) ?></strong> · <strong><?= e($o->status_label) ?></strong>
       · solicitado <?= e($o->requested_at?->format('d/m/Y H:i') ?: '—') ?>
       <?php if ($o->decided_at): ?>· decidido <?= e($o->decided_at->format('d/m/Y H:i')) ?> por <?= e($o->decided_by_name ?: '—') ?><?php endif ?></p>
    <?php if ($o->decision_note): ?><p><strong>Nota de la decisión:</strong> <?= e($o->decision_note) ?></p><?php endif ?>
    <table class="table" style="max-width:640px">
        <tr><td>Razón social</td><td><?= e($o->legal_name ?: '—') ?></td></tr><tr><td>NIT / CI</td><td><?= e($o->document ?: '—') ?></td></tr>
        <tr><td>Contacto</td><td><?= e($o->contact_name ?: '—') ?> · <?= e($o->contact_email ?: '—') ?> · <?= e($o->contact_phone ?: '—') ?></td></tr>
        <tr><td>Ciudad</td><td><?= e($o->city ?: '—') ?></td></tr><tr><td>Descripción</td><td><?= e($o->description ?: '—') ?></td></tr>
        <tr><td>Ventas confirmadas</td><td><?= e(Currency::format($sales)) ?></td></tr><tr><td>Comisión por liquidar</td><td><?= e(Currency::format($comm)) ?></td></tr>
    </table>

    <h5>Eventos asociados (<?= $events->count() ?>)</h5>
    <table class="table"><thead><tr><th>Evento</th><th>Fecha</th><th>Estado</th><th>Moderación</th></tr></thead><tbody>
    <?php foreach ($events as $e): ?><tr><td><a href="<?= Backend::url('aero/tickets/adminevents/update/' . $e->id) ?>"><?= e($e->name) ?></a></td><td><?= e($e->starts_at?->format('d/m/Y H:i')) ?></td>
        <td><?= e(Event::$statuses[$e->status] ?? $e->status) ?></td><td><?= e(['ok' => 'Aprobado', 'pending' => 'En revisión', 'rejected' => 'Rechazado', 'suspended' => 'Suspendido'][$e->moderation] ?? $e->moderation) ?></td></tr><?php endforeach ?>
    <?php if ($events->isEmpty()): ?><tr><td colspan="4" class="text-muted">Sin eventos.</td></tr><?php endif ?></tbody></table>

    <h5>Historial administrativo</h5>
    <table class="table"><thead><tr><th>Fecha</th><th>Acción</th><th>Responsable</th><th>Detalle</th></tr></thead><tbody>
    <?php foreach ($hist as $h): ?><tr><td><?= e($h->created_at?->format('d/m/Y H:i')) ?></td><td><?= e($h->action) ?></td><td><?= e($h->actor_name) ?> <small class="text-muted">(<?= e($h->actor_type) ?>)</small></td>
        <td><?= e($h->note) ?><?php foreach ((array) $h->changes as $f => [$a, $b]): ?> <code><?= e($f) ?>: <?= e($a) ?> → <?= e($b) ?></code><?php endforeach ?></td></tr><?php endforeach ?>
    <?php if ($hist->isEmpty()): ?><tr><td colspan="4" class="text-muted">Sin movimientos.</td></tr><?php endif ?></tbody></table>
</div></div>
