<?php $m = $this->formGetModel(); if (!$m || !$m->exists) return; ?>
<div class="callout callout-info" style="margin-bottom:10px">
    <div class="content">
        <b>Socio:</b> <?= e($m->member?->name) ?> · <b>Plan:</b> <?= e($m->plan?->name ?? '—') ?> · <b>Estado:</b> <?= e($m->getStatusOptions()[$m->status] ?? $m->status) ?>
        <?php if ($m->status === 'active'): ?> · <?= $m->days_left >= 0 ? 'faltan ' . $m->days_left . ' días' : 'vencida hace ' . abs($m->days_left) . ' días' ?><?php endif ?>
        <div style="margin-top:8px">
            <?php if ($m->status === 'pending'): ?>
                <button type="button" class="btn btn-success oc-icon-check" data-request="onMarkPaid" data-request-confirm="¿Marcar como pagada y activar?">Marcar pagada</button>
                <button type="button" class="btn btn-default oc-icon-qrcode" data-request="onIssueCharge">Generar QR de cobro</button>
            <?php endif ?>
            <?php if (in_array($m->status, ['pending', 'active'], true)): ?>
                <button type="button" class="btn btn-default oc-icon-whatsapp" data-request="onSendCharge" data-request-confirm="¿Enviar el cobro por WhatsApp al socio?">Enviar cobro por WhatsApp</button>
                <button type="button" class="btn btn-danger oc-icon-ban" data-request="onCancelMembership" data-request-confirm="¿Cancelar esta membresía?">Cancelar membresía</button>
            <?php endif ?>
            <?php if ($m->status !== 'pending' && $m->plan): ?>
                <button type="button" class="btn btn-default oc-icon-refresh" data-request="onRenew" data-request-confirm="¿Crear la renovación (pendiente de pago)?">Renovar</button>
            <?php endif ?>
        </div>
        <?php if ($m->payment_reference && class_exists(\Aero\Pay\Models\QrCode::class) && ($qr = \Aero\Pay\Models\QrCode::where('internal_reference', $m->payment_reference)->first())): ?>
            <div style="margin-top:8px">QR de cobro: <b><?= e($qr->status) ?></b>
                <?php if ($qr->qr_image): ?> — <a target="_blank" href="<?= url('/api/v1/pay/public/qr/' . $qr->internal_reference . '/image') ?>">ver imagen</a><?php endif ?>
            </div>
        <?php endif ?>
    </div>
</div>
