<?php /** @var \Aero\Medical\Models\Charge $c */ ?>
<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/medical/charges') ?>">Cobros</a></li><li>#<?= (int) $c->id ?></li></ul>
<?php Block::endPut() ?>
<?php if (!$this->fatalError): ?>
<h3 style="margin-top:0">Cobro #<?= (int) $c->id ?> <small class="text-muted">· <?= e($c->status_label) ?></small></h3>
<table class="table" style="max-width:560px">
    <tr><th style="width:160px">Paciente</th><td><?= e($c->patient?->name) ?></td></tr>
    <tr><th>Concepto</th><td><?= e($c->concept) ?></td></tr>
    <tr><th>Monto</th><td><strong><?= e(number_format((float) $c->amount, 2)) ?> <?= e($c->currency) ?></strong></td></tr>
    <tr><th>Forma</th><td><?= e($c->method_label) ?></td></tr>
    <?php if ($c->paid_at): ?><tr><th>Cobrado</th><td><?= e($c->paid_at->format('d/m/Y H:i')) ?></td></tr><?php endif ?>
    <?php if ($c->cancel_reason): ?><tr><th>Motivo de anulación</th><td><?= e($c->cancel_reason) ?></td></tr><?php endif ?>
</table>

<?php if ($c->status === 'pending' && $qr): ?>
    <h4>QR para pagar</h4>
    <?php if ($qr->qr_image): ?><img src="data:image/png;base64,<?= e($qr->qr_image) ?>" alt="QR de pago" style="max-width:260px;background:#fff;padding:8px;border-radius:4px"><?php endif ?>
    <?php if ($qr->redirect_url): ?><p><a href="<?= e($qr->redirect_url) ?>" target="_blank" rel="noopener">Abrir enlace de pago</a></p><?php endif ?>
    <p class="text-muted">Vence el <?= e($qr->due_date ? \Carbon\Carbon::parse($qr->due_date)->format('d/m/Y') : '—') ?>. Cuando el banco confirme el pago, el cobro pasa a «Cobrado» solo.</p>
<?php endif ?>

<div class="form-buttons">
    <?php if ($c->status === 'pending'): ?>
        <button type="button" class="btn btn-success" data-request="onMarkPaid" data-request-confirm="¿Marcar como cobrado a mano? Úselo solo si el pago ya llegó.">Marcar como cobrado</button>
    <?php endif ?>
    <?php if ($c->status !== 'cancelled'): ?>
        <?= Form::open(['style' => 'display:inline-flex;gap:6px']) ?>
            <input type="text" name="reason" class="form-control" placeholder="Motivo (opcional)" maxlength="255" style="width:240px">
            <button type="button" class="btn btn-danger" data-request="onCancel" data-request-confirm="¿Anular este cobro? Si ya estaba cobrado, se anula también su asiento en Finanzas.">Anular</button>
        <?= Form::close() ?>
    <?php endif ?>
    <a class="btn btn-default" href="<?= Backend::url('aero/medical/charges') ?>">Volver</a>
</div>
<?php else: ?>
    <p class="flash-message static error"><?= e($this->fatalError) ?></p>
<?php endif ?>
