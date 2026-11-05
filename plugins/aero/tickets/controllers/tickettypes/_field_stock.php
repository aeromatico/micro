<?php $i = \Aero\Tickets\Classes\Inventory::type($formModel); ?>
<div class="callout callout-<?= $i['sold_out'] ? 'danger' : 'info' ?>"><div class="content">
    <strong><?= $i['sold_out'] ? 'Agotada' : 'Disponible' ?></strong> ·
    Emitidas: <?= $i['issued'] ?> · Reservadas (temporales): <?= $i['reserved'] ?> · Pendientes: <?= $i['pending'] ?> ·
    Cupo: <?= $i['quantity'] ?? 'sin límite propio' ?> · Disponibles ahora: <?= $i['available'] ?? 'sin tope' ?>
    <br><small>Se descuenta también la capacidad total del evento. Si el cupo propio o el del evento se agota, esta entrada se agota.</small>
</div></div>
