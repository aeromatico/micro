<?php
/** @var array $row */
$b = $row['booking'];
$note = $row['note'];
$patients = $row['patients'];
$ready = in_array($b->status, ['confirmed', 'in_service'], true);
?>
<?php if ($note): ?>
    <a class="btn btn-<?= $note->isSigned() ? 'default' : 'primary' ?> btn-sm" href="<?= Backend::url('aero/clinica/consultations/update/' . $note->id) ?>">
        <?= $note->isSigned() ? 'Ver nota firmada' : 'Continuar consulta' ?></a>
<?php elseif ($ready): ?>
    <a class="btn btn-primary btn-sm" href="<?= Backend::url('aero/clinica/today/booking/' . $b->id) ?>">Iniciar consulta</a>
<?php elseif ($b->status === 'pending'): ?>
    <?= Form::open(['style' => 'display:inline']) ?>
        <button type="button" class="btn btn-default btn-sm" data-request="onConfirm" data-request-data="booking_id: <?= (int) $b->id ?>"
            data-request-confirm="¿Confirmar la reserva <?= e($b->code) ?>?">Confirmar reserva</button>
    <?= Form::close() ?>
<?php else: ?>
    <span class="text-muted"><?= e($b->status_label) ?></span>
<?php endif ?>
