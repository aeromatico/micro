<?php
$b = $row['booking'];
$patients = $row['patients'];
$ready = in_array($b->status, ['confirmed', 'in_service'], true) || $row['note'];
?>
<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/clinica/today') ?>">Agenda de hoy</a></li><li><?= e($b->code) ?></li></ul>
<?php Block::endPut() ?>
<div class="form-preview" style="max-width:720px">
    <h3 style="margin-top:0"><?= e($b->code) ?> — <?= e($b->service_name) ?></h3>
    <table class="table" style="max-width:560px">
        <tr><th style="width:160px">Cliente</th><td><?= e($b->customer?->name) ?> · <?= e($b->customer?->phone) ?></td></tr>
        <tr><th>Fecha</th><td><?= e($b->starts_at->format('d/m/Y H:i')) ?> (<?= e($b->status_label) ?>)</td></tr>
        <?php if ($b->customer_notes): ?><tr><th>Motivo</th><td><?= nl2br(e($b->customer_notes)) ?></td></tr><?php endif ?>
    </table>

    <?php if ($row['note']): ?>
        <a class="btn btn-primary" href="<?= Backend::url('aero/clinica/consultations/update/' . $row['note']->id) ?>">Abrir la nota de esta reserva</a>
    <?php elseif (!$ready): ?>
        <p class="flash-message static warning">La reserva está «<?= e($b->status_label) ?>»: solo se inicia la consulta desde una reserva confirmada o en atención.</p>
    <?php else: ?>
        <?= Form::open() ?>
            <?php if (count($patients) > 0): ?>
                <div class="form-group">
                    <label>Paciente</label>
                    <select name="patient_id" class="form-control">
                        <?php foreach ($patients as $p): ?>
                            <option value="<?= (int) $p->id ?>" <?= (int) $row['linked'] === (int) $p->id ? 'selected' : '' ?>><?= e($p->name) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
            <?php else: ?>
                <p class="text-muted">Este cliente aún no tiene ficha de paciente: se creará a partir de sus datos.</p>
            <?php endif ?>

            <?php if (true): ?>
            <button type="button" class="btn btn-primary" data-request="onStart" data-request-data="kind: 'consultation'">Iniciar consulta</button>
            <button type="button" class="btn btn-default" data-request="onStart" data-request-data="kind: 'control'">Iniciar control</button>
            <?php endif ?>
        <?= Form::close() ?>
    <?php endif ?>
</div>
