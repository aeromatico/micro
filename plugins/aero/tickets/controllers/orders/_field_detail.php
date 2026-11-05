<?php
$o = $formModel;
$ev = \Aero\Tickets\Models\Event::withTrashed()->find($o->event_id);
$m = fn ($v) => \Aero\Tickets\Classes\Currency::format($v, $o->currency);
$items = \Aero\Tickets\Models\OrderItem::where('order_id', $o->id)->get();
$tickets = $o->tickets()->orderBy('id')->get();
?>
<div class="callout callout-<?= in_array($o->status, ['confirmed'], true) ? 'success' : (in_array($o->status, ['incident', 'cancelled', 'expired'], true) ? 'danger' : 'info') ?>"><div class="content">
    <p><strong>Orden <?= e($o->code) ?></strong> · <?= e($o->status_label) ?> · <?= e(\Aero\Tickets\Models\Order::$sources[$o->source] ?? $o->source) ?>
       · creada <?= e($o->created_at?->format('d/m/Y H:i')) ?>
       <?php if ($o->status === 'pending_payment' && $o->expires_at): ?>· <strong>reserva vence <?= e($o->expires_at->format('d/m H:i')) ?></strong><?= $o->isLapsed() ? ' (vencida)' : '' ?><?php endif ?>
       <?php if ($o->confirmed_at): ?>· confirmada <?= e($o->confirmed_at->format('d/m H:i')) ?><?php endif ?>
       <?php if ($o->cancelled_at): ?>· cancelada <?= e($o->cancelled_at->format('d/m H:i')) ?><?= $o->cancel_reason ? ' — ' . e($o->cancel_reason) : '' ?><?php endif ?></p>
    <p><strong>Evento:</strong> <?= e($ev?->name) ?></p>
    <p><strong>Comprador:</strong> <?= e($o->buyer_name) ?> · <?= e($o->buyer_email) ?> · <?= e($o->buyer_phone) ?><?= $o->buyer_document ? ' · CI ' . e($o->buyer_document) : '' ?></p>
    <?php if ($o->buyer_extra): ?><p><strong><?= e($ev?->buyer_extra_label ?: 'Información adicional') ?>:</strong> <?= e($o->buyer_extra) ?></p><?php endif ?>
    <?php if ($o->payment_reference): ?><p><strong>Referencia de pago informada:</strong> <?= e($o->payment_reference) ?></p><?php endif ?>
    <?php if ($o->incident_note): ?><p><strong>Incidencia:</strong> <?= e($o->incident_note) ?></p><?php endif ?>
    <table class="table"><thead><tr><th>Entrada</th><th>Cant.</th><th>Precio base</th><th>Comisión</th><th>Total</th></tr></thead><tbody>
    <?php foreach ($items as $i): ?>
        <tr><td><?= e($i->name) ?></td><td><?= (int) $i->quantity ?></td><td><?= e($m($i->unit_price)) ?></td><td><?= e($m($i->unit_fee)) ?></td><td><?= e($m($i->total)) ?></td></tr>
    <?php endforeach ?>
        <tr><td colspan="2"><strong>Subtotal / comisiones / total</strong></td><td><?= e($m($o->subtotal)) ?></td><td><?= e($m($o->fees)) ?></td><td><strong><?= e($m($o->total)) ?></strong></td></tr>
    </tbody></table>
    <table class="table"><thead><tr><th>Asistente</th><th>Código</th><th>Estado</th></tr></thead><tbody>
    <?php foreach ($tickets as $t): ?>
        <tr><td><?= e($t->holder_name) ?></td><td><?= e($t->code) ?></td><td><?= e(\Aero\Tickets\Models\Ticket::$statuses[$t->status]) ?><?= $t->refund_pending ? ' · reembolso pendiente' : '' ?></td></tr>
    <?php endforeach ?>
    </tbody></table>
</div></div>
