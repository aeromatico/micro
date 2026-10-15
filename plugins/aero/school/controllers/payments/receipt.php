<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/school/payments') ?>">Pagos y recibos</a></li><li>Recibo N° <?= (int) $payment->receipt_no ?></li></ul>
<?php Block::endPut() ?>
<?php
$cur = $settings->currency ?: 'BOB';
$schoolName = $settings->school_name ?: \Aero\School\Classes\Notifier::schoolName($payment->tenant_id);
$no = str_pad((string) $payment->receipt_no, 6, '0', STR_PAD_LEFT);
$student = $payment->student;
$receiver = $payment->received_by ? \Backend\Models\User::find($payment->received_by) : null;
$balance = $student && !$payment->voided_at ? $student->pendingBalance() : null;
?>
<style>
.school-receipt{max-width:760px;margin:0 auto 20px;background:#fff;color:#1f2937;border:1px solid #cbd5e1;padding:36px 40px;position:relative;font-family:Georgia,'Times New Roman',serif;font-size:14px;line-height:1.5}
.school-receipt .rc-head{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:3px double #1f2937;padding-bottom:14px;margin-bottom:18px}
.school-receipt .rc-school{font-size:20px;font-weight:bold;text-transform:uppercase;letter-spacing:.5px}
.school-receipt .rc-meta{font-size:12px;color:#475569;margin-top:2px}
.school-receipt .rc-no{text-align:right}
.school-receipt .rc-no small{display:block;font-size:11px;letter-spacing:2px;color:#475569;text-transform:uppercase}
.school-receipt .rc-no strong{font-size:22px;font-family:'Courier New',monospace;color:#b91c1c}
.school-receipt .rc-title{text-align:center;font-size:17px;letter-spacing:6px;text-transform:uppercase;margin:6px 0 18px}
.school-receipt dl{display:grid;grid-template-columns:150px 1fr;gap:6px 12px;margin:0 0 18px}
.school-receipt dt{font-weight:normal;color:#475569}
.school-receipt dd{margin:0;border-bottom:1px dotted #94a3b8;padding-bottom:2px}
.school-receipt table{width:100%;border-collapse:collapse;margin-bottom:10px}
.school-receipt th{background:#f1f5f9;border-top:1px solid #1f2937;border-bottom:1px solid #1f2937;padding:7px 10px;text-align:left;font-size:12px;text-transform:uppercase;letter-spacing:1px}
.school-receipt td{padding:7px 10px;border-bottom:1px solid #e2e8f0}
.school-receipt .num{text-align:right;font-variant-numeric:tabular-nums}
.school-receipt .rc-total td{border-top:2px solid #1f2937;border-bottom:none;font-weight:bold;font-size:15px}
.school-receipt .rc-words{margin:12px 0 4px;padding:8px 12px;background:#f8fafc;border-left:4px solid #1f2937}
.school-receipt .rc-sign{display:flex;justify-content:space-around;margin-top:70px;text-align:center;font-size:12px;color:#475569}
.school-receipt .rc-sign div{width:36%;border-top:1px solid #1f2937;padding-top:6px}
.school-receipt .rc-foot{margin-top:26px;font-size:11px;color:#64748b;text-align:center}
.school-receipt .rc-void{position:absolute;top:42%;left:50%;transform:translate(-50%,-50%) rotate(-18deg);font-size:84px;font-weight:bold;color:rgba(185,28,28,.22);border:8px solid rgba(185,28,28,.22);padding:0 30px;letter-spacing:8px;pointer-events:none}
@media print{
  body *{visibility:hidden}
  .school-receipt,.school-receipt *{visibility:visible}
  .school-receipt{position:absolute;left:0;top:0;width:100%;max-width:none;border:none;padding:10mm 12mm;margin:0}
  @page{size:A4;margin:10mm}
}
</style>
<div class="school-receipt">
    <?php if ($payment->voided_at): ?><div class="rc-void">ANULADO</div><?php endif ?>
    <div class="rc-head">
        <div>
            <div class="rc-school"><?= e($schoolName) ?></div>
            <div class="rc-meta">
                <?php if ($settings->sie_code): ?>Código SIE <?= e($settings->sie_code) ?><?php endif ?>
                <?php if ($settings->district): ?> · <?= e($settings->district) ?><?php endif ?>
                <?php if ($settings->city): ?> · <?= e($settings->city) ?><?php endif ?>
            </div>
        </div>
        <div class="rc-no"><small>Recibo N°</small><strong><?= $no ?></strong></div>
    </div>

    <div class="rc-title">Recibo de pago</div>

    <dl>
        <dt>Fecha y hora</dt><dd><?= $payment->paid_at?->format('d/m/Y H:i') ?></dd>
        <dt>Recibí de</dt><dd><?= e($payment->guardian?->name ?: '—') ?></dd>
        <dt>Estudiante</dt><dd><strong><?= e($student?->full_name) ?></strong><?php if ($student?->rude): ?> · RUDE <?= e($student->rude) ?><?php endif ?></dd>
        <?php if ($student?->course): ?><dt>Curso</dt><dd><?= e($student->course->name) ?> — <?= e($student->course->level_label) ?></dd><?php endif ?>
        <dt>Forma de pago</dt><dd><?= e(\Aero\School\Models\Payment::METHODS[$payment->method] ?? $payment->method) ?><?= $payment->reference ? ' · Ref. ' . e($payment->reference) : '' ?></dd>
    </dl>

    <table>
        <thead><tr><th style="width:36px">N°</th><th>Concepto</th><th class="num" style="width:130px">Importe (<?= e($cur) ?>)</th></tr></thead>
        <tbody>
        <?php foreach ($payment->items as $i => $item): ?>
            <tr><td><?= $i + 1 ?></td><td><?= e($item->charge?->concept) ?></td><td class="num"><?= number_format((float) $item->amount, 2) ?></td></tr>
        <?php endforeach ?>
        <tr class="rc-total"><td></td><td class="num">TOTAL</td><td class="num"><?= number_format((float) $payment->amount, 2) ?></td></tr>
        </tbody>
    </table>

    <div class="rc-words"><strong>Son:</strong> <?= e(\Aero\School\Classes\AmountInWords::money((float) $payment->amount, $cur)) ?>.</div>
    <?php if ($payment->notes): ?><p style="font-size:12px;color:#475569;margin:8px 0 0"><strong>Observaciones:</strong> <?= e($payment->notes) ?></p><?php endif ?>
    <?php if ($balance !== null): ?><p style="font-size:12px;color:#475569;margin:8px 0 0">Saldo pendiente del estudiante tras este pago: <strong><?= number_format($balance, 2) ?> <?= e($cur) ?></strong></p><?php endif ?>

    <div class="rc-sign">
        <div>Recibí conforme<br><?= e($payment->guardian?->name) ?></div>
        <div>Entregué conforme<br><?= e($receiver?->full_name ?? $receiver?->login ?? $settings->director) ?></div>
    </div>
    <div class="rc-foot">Documento generado el <?= now()->format('d/m/Y H:i') ?> · <?= e($schoolName) ?></div>
</div>
<div style="margin-top:15px">
    <button type="button" class="btn btn-primary" onclick="window.print()">Imprimir / Guardar PDF</button>
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
