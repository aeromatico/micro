<?php
$labels = \Aero\Church\Models\Contribution::STATUSES;
$canQr = $model->method === 'qr' && $model->status === 'pending';
?>
<div class="form-group" style="margin-top:20px">
    <h4>Estado: <?= e($labels[$model->status] ?? $model->status) ?><?= $model->receipt_number ? ' · Comprobante ' . e($model->receipt_number) : '' ?></h4>

    <?php if ($canQr): ?>
        <?php if ($model->payment_reference): ?>
            <p><img src="<?= e(url('/api/v1/pay/public/qr/' . $model->payment_reference . '/image')) ?>" alt="QR de pago" style="max-width:240px;border:1px solid #ccc"></p>
            <p class="text-muted">Se confirma solo cuando el banco avisa el pago.</p>
        <?php else: ?>
            <p class="text-muted">Aún no hay QR. Elige la cuenta bancaria en Aportes → Configuración y genera el código.</p>
        <?php endif ?>
        <button type="button" class="btn btn-default" data-request="onIssueQr"><?= $model->payment_reference ? 'Volver a mostrar el QR' : 'Generar QR' ?></button>
        <button type="button" class="btn btn-default" data-request="onConfirmManual" data-request-confirm="¿Confirmar que este aporte ya se recibió?">Confirmar pago manualmente</button>
    <?php endif ?>

    <?php if ($model->status === 'confirmed' && !$model->is_anonymous): ?>
        <?php if ($model->receipt_number): ?>
            <a class="btn btn-default" target="_blank" href="<?= Backend::url('aero/church/contributions/receipt/' . $model->id) ?>">Ver comprobante</a>
        <?php else: ?>
            <button type="button" class="btn btn-default" data-request="onIssueReceipt">Emitir comprobante</button>
        <?php endif ?>
    <?php endif ?>

    <?php if ($model->status !== 'cancelled'): ?>
        <button type="button" class="btn btn-danger" data-request="onCancelContribution" data-request-confirm="¿Anular este aporte? <?= $model->status === 'confirmed' ? 'También se anula su asiento en Finanzas.' : '' ?>">Anular aporte</button>
    <?php endif ?>
</div>
