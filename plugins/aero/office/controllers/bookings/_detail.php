<?php
$u = BackendAuth::getUser();
$front = \Aero\Office\Classes\CurrentTenant::isAdmin() || $u->hasAccess('aero.office.use') || $u->hasAccess('aero.office.reception');
$final = $b->isFinal();
$st = $b->status;
$workers = $final ? [] : $this->workerChoices($b);
?>
<div class="form-preview" style="margin-bottom:20px">
    <h3 style="margin-top:0">
        <?= e($b->code) ?> — <?= e($b->service_name) ?>
        <?= $this->makePartial('~/plugins/aero/office/models/booking/_status.php', ['record' => $b]) ?>
    </h3>
    <table class="table" style="max-width:720px">
        <tr><th style="width:180px">Cliente</th><td><?= e($b->customer?->name) ?> · <?= e($b->customer?->phone) ?> <?= e($b->customer?->email) ?></td></tr>
        <tr><th>Fecha</th><td><?= e($b->starts_at->translatedFormat('l d/m/Y H:i')) ?> – <?= e($b->ends_at->format('H:i')) ?> (<?= (int) $b->duration_minutes ?> min)</td></tr>
        <tr><th>Profesional</th><td><?= e($b->worker?->name ?: $b->worker_name) ?></td></tr>
        <tr><th>Sucursal</th><td><?= e($b->branch?->name) ?></td></tr>
        <tr><th>Precio</th><td><?= e(number_format((float) $b->price, 2)) ?> <?= e($b->currency) ?> <small class="text-muted">(del momento de la reserva)</small></td></tr>
        <tr><th>Origen</th><td><?= $b->source === 'public' ? 'Portal público' : 'Manual' ?></td></tr>
        <?php if ($b->customer_notes): ?><tr><th>Notas del cliente</th><td><?= nl2br(e($b->customer_notes)) ?></td></tr><?php endif ?>
        <?php if ($b->cancel_reason): ?><tr><th>Motivo</th><td><?= e($b->cancel_reason) ?></td></tr><?php endif ?>
    </table>

    <?php if (!$final): ?>
    <div style="display:flex;flex-wrap:wrap;gap:6px;margin:10px 0">
        <?php if ($front && $st === 'pending'): ?>
            <button class="btn btn-success" data-request="onConfirm" data-request-confirm="¿Confirmar esta reserva?">Confirmar</button>
            <button class="btn btn-warning" data-request="onReject" data-request-confirm="¿Rechazar la solicitud?">Rechazar</button>
        <?php endif ?>
        <?php if ($st === 'confirmed'): ?>
            <button class="btn btn-primary" data-request="onStart">Iniciar atención</button>
        <?php endif ?>
        <?php if (in_array($st, ['confirmed', 'in_service'], true)): ?>
            <button class="btn btn-success" data-request="onComplete">Completar</button>
        <?php endif ?>
        <?php if ($front && $st === 'confirmed'): ?>
            <button class="btn btn-default" data-request="onNoShow" data-request-confirm="¿Marcar como «No asistió»?">No asistió</button>
        <?php endif ?>
        <?php if ($front): ?>
            <button class="btn btn-danger" data-request="onCancel" data-request-confirm="¿Cancelar la reserva? El horario quedará libre.">Cancelar</button>
        <?php endif ?>
    </div>

    <?php if ($front): ?>
    <div class="row" style="max-width:720px;margin-top:10px">
        <form class="col-sm-6" data-request="onReschedule">
            <label>Reprogramar a</label>
            <input type="datetime-local" name="starts_at" class="form-control" value="<?= e($b->starts_at->format('Y-m-d\TH:i')) ?>">
            <button type="submit" class="btn btn-default btn-sm" style="margin-top:6px">Reprogramar</button>
        </form>
        <?php if ($workers): ?>
        <form class="col-sm-6" data-request="onReassign">
            <label>Reasignar a</label>
            <select name="worker_id" class="form-control">
                <?php foreach ($workers as $id => $name): ?>
                    <option value="<?= (int) $id ?>" <?= (int) $id === (int) $b->worker_id ? 'selected' : '' ?>><?= e($name) ?></option>
                <?php endforeach ?>
            </select>
            <button type="submit" class="btn btn-default btn-sm" style="margin-top:6px">Reasignar</button>
        </form>
        <?php endif ?>
    </div>
    <?php endif ?>
    <?php endif ?>

    <h4 style="margin-top:24px">Historial</h4>
    <table class="table table-condensed" style="max-width:720px">
        <?php foreach ($b->logs()->orderBy('id', 'desc')->get() as $log): ?>
        <tr>
            <td style="width:140px"><?= e($log->created_at?->format('d/m/Y H:i')) ?></td>
            <td style="width:150px"><strong><?= e($log->action_label) ?></strong></td>
            <td><small class="text-muted"><?= e(['staff' => 'personal', 'customer' => 'cliente', 'system' => 'sistema'][$log->actor] ?? $log->actor) ?>
                <?= $log->note ? ' — ' . e($log->note) : '' ?>
                <?php if (is_array($log->changes)): ?> · <?= e(json_encode($log->changes, JSON_UNESCAPED_UNICODE)) ?><?php endif ?></small></td>
        </tr>
        <?php endforeach ?>
    </table>
</div>
