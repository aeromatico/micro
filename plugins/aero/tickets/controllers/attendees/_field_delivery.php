<?php $t = $formModel; $ev = \Aero\Tickets\Models\Event::withTrashed()->find($t->event_id); ?>
<div class="callout callout-info"><div class="content">
    <p><strong>Estado para el asistente:</strong> <?= e($t->state_label) ?><?= $t->seq ? ' · entrada n.º ' . (int) $t->seq : '' ?><?= $t->checked_in_at ? ' · ingresó ' . e($t->checked_in_at->format('d/m H:i')) : '' ?></p>
    <?php if (in_array($t->status, ['valid', 'used'], true)): ?>
        <p><strong>Enlace privado:</strong> <input type="text" readonly class="form-control" value="<?= e($t->public_url) ?>" onclick="this.select()"></p>
        <p><a class="btn btn-default" target="_blank" href="<?= Backend::url('aero/tickets/attendees/pdf/' . (int) $t->id) ?>">Ver PDF</a></p>
    <?php else: ?>
        <p class="text-muted">La entrada se emite cuando el pago o la aprobación se confirman.</p>
    <?php endif ?>
</div></div>
