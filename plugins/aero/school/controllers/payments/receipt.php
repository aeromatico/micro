<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/school/payments') ?>">Pagos y recibos</a></li><li>Recibo N° <?= (int) $payment->receipt_no ?></li></ul>
<?php Block::endPut() ?>
<?php $cur = $settings->currency ?: 'BOB'; ?>
<div class="scoreboard"><div class="form-preview" style="max-width:720px;background:#fff;padding:24px;border:1px solid #ddd">
    <h3 style="margin-top:0"><?= e($settings->school_name ?: \Aero\School\Classes\Notifier::schoolName($payment->tenant_id)) ?></h3>
    <p><strong>Recibo N° <?= str_pad((string) $payment->receipt_no, 6, '0', STR_PAD_LEFT) ?></strong>
        <?= $payment->voided_at ? '<span class="text-danger"> — ANULADO</span>' : '' ?><br>
        Fecha: <?= $payment->paid_at?->format('d/m/Y H:i') ?></p>
    <p>Estudiante: <strong><?= e($payment->student?->full_name) ?></strong>
        <?php if ($payment->student?->course): ?> — <?= e($payment->student->course->name) ?><?php endif ?><br>
        <?php if ($payment->guardian): ?>Recibido de: <?= e($payment->guardian->name) ?><br><?php endif ?>
        Medio: <?= e(\Aero\School\Models\Payment::METHODS[$payment->method] ?? $payment->method) ?>
        <?= $payment->reference ? ' · Ref. ' . e($payment->reference) : '' ?></p>
    <table class="table data" style="width:100%">
        <thead><tr><th>Concepto</th><th style="text-align:right">Monto</th></tr></thead>
        <tbody>
        <?php foreach ($payment->items as $item): ?>
            <tr><td><?= e($item->charge?->concept) ?></td><td style="text-align:right"><?= number_format((float) $item->amount, 2) ?></td></tr>
        <?php endforeach ?>
        </tbody>
        <tfoot><tr><th>Total <?= e($cur) ?></th><th style="text-align:right"><?= number_format((float) $payment->amount, 2) ?></th></tr></tfoot>
    </table>
    <?php if ($payment->notes): ?><p class="text-muted"><?= e($payment->notes) ?></p><?php endif ?>
</div></div>
<div style="margin-top:15px">
    <button type="button" class="btn btn-primary" onclick="window.print()">Imprimir</button>
    <?php if (!$payment->voided_at): ?>
    <?= Form::open(['style' => 'display:inline']) ?>
        <input type="hidden" name="reason" id="void-reason">
        <button type="button" class="btn btn-danger" data-request="onVoid"
            data-request-confirm="¿Anular este pago? Las cuotas volverán a quedar pendientes."
            onclick="document.getElementById('void-reason').value = prompt('Motivo de la anulación (opcional)') || ''">Anular pago</button>
    <?= Form::close() ?>
    <?php endif ?>
    <a href="<?= Backend::url('aero/school/payments') ?>" class="btn btn-default">Volver</a>
</div>
