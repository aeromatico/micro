<?php
use Aero\Tickets\Classes\Currency;
use Aero\Tickets\Models\{Audit, Event, Order, Payment, Ticket};
$b = $formModel;
$orders = Order::where('buyer_email', $b->email)->orderByDesc('id')->limit(50)->get();
$spent = (float) Order::where('buyer_email', $b->email)->where('status', 'confirmed')->sum('total');
$tickets = Ticket::whereIn('order_id', Order::where('buyer_email', $b->email)->pluck('id'))->whereIn('status', ['valid', 'used'])->count();
$flags = Payment::whereIn('order_id', Order::where('buyer_email', $b->email)->pluck('id'))->where(fn ($q) => $q->where('is_suspicious', true)->orWhere('status', 'rejected'))->count();
$hist = Audit::where('subject_type', 'buyer')->where('subject_id', $b->id)->orderByDesc('id')->limit(30)->get();
?>
<div class="callout callout-<?= $b->status === 'blocked' ? 'danger' : 'info' ?>"><div class="content">
    <p><strong><?= e($b->name ?: $b->email) ?></strong> · <?= e($b->email) ?> · <?= e($b->phone) ?> · <strong><?= e(\Aero\Tickets\Models\Buyer::$statuses[$b->status]) ?></strong></p>
    <?php if ($b->status === 'blocked'): ?><p class="text-danger">Bloqueado <?= e($b->blocked_at?->format('d/m/Y H:i')) ?> por <?= e($b->blocked_by) ?>: <?= e($b->blocked_reason) ?></p><?php endif ?>
    <p>Órdenes: <strong><?= (int) $b->orders_count ?></strong> · Entradas vigentes: <strong><?= $tickets ?></strong> · Gastado (confirmado): <strong><?= e(Currency::format($spent)) ?></strong> · Pagos rechazados o sospechosos: <strong><?= $flags ?></strong></p>
    <table class="table"><thead><tr><th>Orden</th><th>Evento</th><th>Estado</th><th>Total</th><th>Fecha</th></tr></thead><tbody>
    <?php foreach ($orders as $o): ?><tr><td><a href="<?= Backend::url('aero/tickets/orders/update/' . $o->id) ?>"><?= e($o->code) ?></a></td><td><?= e(Event::withTrashed()->find($o->event_id)?->name) ?></td>
        <td><?= e($o->status_label) ?></td><td><?= e(Currency::format($o->total, $o->currency)) ?></td><td><?= e($o->created_at?->format('d/m/Y H:i')) ?></td></tr><?php endforeach ?></tbody></table>
    <?php if ($hist->count()): ?><h5>Historial administrativo</h5><ul><?php foreach ($hist as $h): ?><li><?= e($h->created_at?->format('d/m/Y H:i')) ?> — <?= e($h->action) ?> por <?= e($h->actor_name) ?>: <?= e($h->note) ?></li><?php endforeach ?></ul><?php endif ?>
</div></div>
