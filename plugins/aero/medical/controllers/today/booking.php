<?php
$b = $row['booking'];
$patients = $row['patients'];
$ready = in_array($b->status, ['confirmed', 'in_service'], true) || $row['note'];
$mode = \Aero\Medical\Classes\Mode::forTenant($b->tenant_id);
$guardian = \Aero\Medical\Classes\Mode::term('guardian', $mode);
?>
<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/medical/today') ?>">Agenda de hoy</a></li><li><?= e($b->code) ?></li></ul>
<?php Block::endPut() ?>
<div class="form-preview" style="max-width:720px">
    <h3 style="margin-top:0"><?= e($b->code) ?> — <?= e($b->service_name) ?></h3>
    <table class="table" style="max-width:560px">
        <tr><th style="width:160px"><?= e($guardian) ?> (cliente)</th><td><?= e($b->customer?->name) ?> · <?= e($b->customer?->phone) ?></td></tr>
        <tr><th>Fecha</th><td><?= e($b->starts_at->format('d/m/Y H:i')) ?> (<?= e($b->status_label) ?>)</td></tr>
        <?php if ($b->customer_notes): ?><tr><th>Motivo</th><td><?= nl2br(e($b->customer_notes)) ?></td></tr><?php endif ?>
    </table>

    <?php if ($row['note']): ?>
        <a class="btn btn-primary" href="<?= Backend::url('aero/medical/consultations/update/' . $row['note']->id) ?>">Abrir la nota de esta reserva</a>
    <?php elseif (!$ready): ?>
        <p class="flash-message static warning">La reserva está «<?= e($b->status_label) ?>»: solo se inicia la consulta desde una reserva confirmada o en atención.</p>
    <?php else: ?>
        <?= Form::open() ?>
            <?php if (count($patients) > 0): ?>
                <div class="form-group">
                    <label>Paciente</label>
                    <select name="patient_id" class="form-control">
                        <?php foreach ($patients as $p): ?>
                            <option value="<?= (int) $p->id ?>" <?= (int) $row['linked'] === (int) $p->id ? 'selected' : '' ?>><?= e($p->name) ?><?= $p->species ? ' (' . e(\Aero\Medical\Classes\Mode::species()[$p->species] ?? $p->species) . ')' : '' ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
            <?php elseif ($mode === \Aero\Medical\Classes\Mode::ANIMAL): ?>
                <p class="flash-message static warning"><?= e($guardian) ?> sin animales registrados.
                    <a href="<?= Backend::url('aero/medical/patients/create?customer_id=' . (int) $b->customer_id) ?>">Registrar el animal</a></p>
            <?php else: ?>
                <p class="text-muted">Este cliente aún no tiene ficha de paciente: se creará a partir de sus datos.</p>
            <?php endif ?>

            <?php if (count($patients) > 0 || $mode !== \Aero\Medical\Classes\Mode::ANIMAL): ?>
            <button type="button" class="btn btn-primary" data-request="onStart" data-request-data="kind: 'consultation'">Iniciar consulta</button>
            <button type="button" class="btn btn-default" data-request="onStart" data-request-data="kind: 'control'">Iniciar control</button>
            <?php endif ?>
        <?= Form::close() ?>
    <?php endif ?>
</div>
