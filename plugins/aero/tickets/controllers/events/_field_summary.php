<?php
$ev = $formModel;
$rev = $ev->revenue();
$inv = \Aero\Tickets\Classes\Inventory::event($ev);
$types = $ev->ticket_types()->get();
?>
<div class="callout callout-info"><div class="content">
    <p><strong>Estado:</strong> <?= e(\Aero\Tickets\Models\Event::$statuses[$ev->status] ?? $ev->status) ?>
       <?= $inv['sold_out'] ? ' · <strong>AGOTADO</strong>' : '' ?></p>
    <p><strong>Capacidad:</strong> <?= $inv['capacity'] ?? 'sin tope' ?>
       · <strong>Emitidas:</strong> <?= $inv['issued'] ?>
       · <strong>Reservadas:</strong> <?= $inv['reserved'] ?>
       · <strong>Pendientes:</strong> <?= $inv['pending'] ?>
       · <strong>Disponibles:</strong> <?= $inv['available'] ?? 'sin tope' ?>
       · <strong>Ingresaron:</strong> <?= $inv['used'] ?></p>
    <p><strong>Ventas confirmadas:</strong> <?= $rev ? e(implode(' + ', array_map(fn ($c, $v) => \Aero\Tickets\Classes\Currency::format($v, $c), array_keys($rev), $rev))) : '0' ?></p>
    <?php $ps = $ev->paymentStats(); $fmt = fn ($a) => $a ? implode(' + ', array_map(fn ($c, $v) => \Aero\Tickets\Classes\Currency::format($v, $c), array_keys($a), $a)) : '0'; ?>
    <p><strong>Cobros aprobados:</strong> <?= e($fmt($ps['approved'])) ?>
       · <strong>Por verificar:</strong> <?= (int) $ps['to_verify'] ?>
       · <strong>Reembolsos pendientes:</strong> <?= (int) $ps['refund_pending'] ?><?= $ps['refund_amount'] ? ' (' . e($fmt($ps['refund_amount'])) . ')' : '' ?>
       · <strong>Tardíos:</strong> <?= (int) $ps['late'] ?> · <strong>Sospechosos:</strong> <?= (int) $ps['suspicious'] ?>
       · <a href="<?= Backend::url('aero/tickets/payments') ?>">ver pagos</a></p>
    <?php $gs = $ev->guests()->where('status', 'active')->selectRaw('rsvp, COUNT(*) c, SUM(confirmed_count) people')->groupBy('rsvp')->get()->keyBy('rsvp'); ?>
    <?php if ($gs->isNotEmpty() || $ev->guests()->exists()): ?>
        <p><strong>Invitados:</strong> sin respuesta <?= (int) ($gs['pending']->c ?? 0) ?> · confirmaron <?= (int) ($gs['confirmed']->c ?? 0) ?> (<?= (int) ($gs['confirmed']->people ?? 0) ?> personas) · rechazaron <?= (int) ($gs['declined']->c ?? 0) ?> ·
           revocadas <?= $ev->guests()->where('status', 'revoked')->count() ?> · <a href="<?= Backend::url('aero/tickets/guests?event_id=' . $ev->id) ?>">ver lista</a></p>
    <?php endif ?>
    <?php if ($types->count()): ?>
        <ul>
        <?php foreach ($types as $t): ?>
            <li><?= e($t->name) ?> — <?= e($t->price_summary) ?>: <?= e($t->stock_summary) ?></li>
        <?php endforeach ?>
        </ul>
    <?php endif ?>
    <?php if ($ev->share_url): ?>
        <p><strong>Enlace para compartir:</strong> <input type="text" readonly class="form-control" value="<?= e($ev->share_url) ?>" onclick="this.select()"></p>
    <?php elseif ($ev->visibility === 'invite'): ?>
        <p>Evento por invitación: comparte el enlace individual de cada invitado desde <a href="<?= Backend::url('aero/tickets/guests') ?>">Invitados</a>.</p>
    <?php endif ?>
    <p>
        <a href="<?= Backend::url('aero/tickets/tickettypes/create') ?>">+ Tipo de entrada</a> ·
        <a href="<?= Backend::url('aero/tickets/guests/create') ?>">+ Invitado</a> ·
        <a href="<?= Backend::url('aero/tickets/attendees?event_id=' . $ev->id) ?>">Ver asistentes</a> ·
        <a href="<?= Backend::url('aero/tickets/checkin?event=' . $ev->id) ?>">Control de ingreso</a>
    </p>
</div></div>
