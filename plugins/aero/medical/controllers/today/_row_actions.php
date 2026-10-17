<?php
/** @var array $row */
$b = $row['booking'];
$note = $row['note'];
$patients = $row['patients'];
$ready = in_array($b->status, ['confirmed', 'in_service'], true);
?>
<?php if ($note): ?>
    <a class="btn btn-<?= $note->isSigned() ? 'default' : 'primary' ?> btn-sm" href="<?= Backend::url('aero/medical/consultations/update/' . $note->id) ?>">
        <?= $note->isSigned() ? 'Ver nota firmada' : 'Continuar consulta' ?></a>
<?php elseif ($ready): ?>
    <a class="btn btn-primary btn-sm" href="<?= Backend::url('aero/medical/today/booking/' . $b->id) ?>">Iniciar consulta</a>
<?php else: ?>
    <span class="text-muted">Confirme la reserva</span>
<?php endif ?>
