<?php
use Aero\Tickets\Classes\{Currency, Inventory};
use Aero\Tickets\Models\{Audit, Event, TicketType};
$e = $formModel;
$inv = Inventory::event($e);
$types = TicketType::where('event_id', $e->id)->orderBy('sort_order')->get();
$ps = $e->paymentStats();
$hist = Audit::where('event_id', $e->id)->orderByDesc('id')->limit(100)->get();
$fmt = fn ($a) => $a ? implode(' + ', array_map(fn ($c, $v) => Currency::format($v, $c), array_keys($a), $a)) : Currency::format(0);
$cls = ['ok' => 'info', 'pending' => 'warning', 'rejected' => 'danger', 'suspended' => 'danger'][$e->moderation] ?? 'info';
?>
<div class="callout callout-<?= $cls ?>"><div class="content">
    <p><strong><?= e($e->name) ?></strong> · <?= e(Event::$statuses[$e->status] ?? $e->status) ?> · moderación: <strong><?= e($e->moderation_label) ?></strong>
       <?php if ($e->moderation_note): ?> — <?= e($e->moderation_note) ?> (<?= e($e->moderated_by) ?>, <?= e($e->moderated_at?->format('d/m H:i')) ?>)<?php endif ?></p>
    <?php if ($block = $e->platformBlocked()): ?><p class="text-danger">⚠ <?= e($block) ?></p><?php endif ?>
    <table class="table" style="max-width:720px">
        <tr><td>Organizador</td><td><?= e($e->organizer_name_label) ?></td></tr>
        <tr><td>Modalidad</td><td><?= e(Event::$visibilities[$e->visibility] ?? $e->visibility) ?></td></tr>
        <tr><td>Fecha</td><td><?= e($e->starts_at?->format('d/m/Y H:i')) ?> — <?= e($e->ends_at?->format('d/m/Y H:i') ?: 'sin hora de fin') ?></td></tr>
        <tr><td>Lugar</td><td><?= e(implode(', ', array_filter([$e->venue_name, $e->address, $e->zone, $e->city, $e->department]))) ?></td></tr>
        <tr><td>Contacto</td><td><?= e(implode(' · ', array_filter([$e->contact_name, $e->contact_phone, $e->contact_email]))) ?></td></tr>
        <tr><td>Descripción</td><td style="white-space:pre-line"><?= e($e->description) ?></td></tr>
    </table>

    <h5>Capacidad y entradas</h5>
    <p>Capacidad: <strong><?= $inv['capacity'] ?? 'sin tope' ?></strong> · Emitidas: <strong><?= $inv['issued'] ?></strong> · Reservadas: <strong><?= $inv['reserved'] ?></strong> · Pendientes: <strong><?= $inv['pending'] ?></strong>
       · Disponibles: <strong><?= $inv['available'] ?? 'sin tope' ?></strong> · Ingresaron: <strong><?= $inv['used'] ?></strong></p>
    <table class="table"><thead><tr><th>Entrada</th><th>Tipo</th><th>Precio final</th><th>Disponibilidad</th></tr></thead><tbody>
    <?php foreach ($types as $t): ?><tr><td><?= e($t->name) ?></td><td><?= e(TicketType::$kinds[$t->kind] ?? '') ?></td><td><?= e($t->price_summary) ?></td><td><?= e($t->stock_summary) ?></td></tr><?php endforeach ?>
    <?php if ($types->isEmpty()): ?><tr><td colspan="4" class="text-muted">Sin tipos de entrada.</td></tr><?php endif ?></tbody></table>
    <p>Cobros aprobados: <strong><?= e($fmt($ps['approved'])) ?></strong> · Por verificar: <strong><?= $ps['to_verify'] ?></strong> · Reembolsos pendientes: <strong><?= $ps['refund_pending'] ?></strong> ·
       <a href="<?= Backend::url('aero/tickets/orders?event_id=' . $e->id) ?>">órdenes</a> · <a href="<?= Backend::url('aero/tickets/payments') ?>">pagos</a></p>

    <h5>Historial de modificaciones relevantes</h5>
    <table class="table"><thead><tr><th>Fecha</th><th>Acción</th><th>Responsable</th><th>Cambios</th></tr></thead><tbody>
    <?php foreach ($hist as $h): ?><tr><td><?= e($h->created_at?->format('d/m/Y H:i')) ?></td><td><?= e($h->action) ?></td><td><?= e($h->actor_name) ?> <small class="text-muted">(<?= e($h->actor_type) ?>)</small></td>
        <td><?= e($h->note) ?><?php foreach ((array) $h->changes as $f => [$a, $b]): ?> <code><?= e($f) ?>: <?= e(mb_substr((string) $a, 0, 60)) ?> → <?= e(mb_substr((string) $b, 0, 60)) ?></code><?php endforeach ?></td></tr><?php endforeach ?>
    <?php if ($hist->isEmpty()): ?><tr><td colspan="4" class="text-muted">Sin modificaciones registradas.</td></tr><?php endif ?></tbody></table>
</div></div>
