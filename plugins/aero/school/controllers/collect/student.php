<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/school/collect') ?>">Cobrar</a></li><li><?= e($student->full_name) ?></li></ul>
<?php Block::endPut() ?>
<?php $cur = $settings->currency ?: 'BOB'; $open = $statement['charges']->filter(fn ($c) => in_array($c->status, ['pending', 'partial'], true)); ?>
<div class="callout callout-<?= $statement['overdue'] > 0 ? 'danger' : 'info' ?>" style="margin-bottom:15px">
    <div class="header"><h3><?= e($student->full_name) ?> — <?= e($student->course?->name ?? 'sin curso') ?></h3></div>
    <div class="content">
        Facturado <strong><?= number_format($statement['billed'], 2) ?></strong> ·
        Pagado <strong><?= number_format($statement['paid'], 2) ?></strong> ·
        Saldo <strong><?= number_format($statement['balance'], 2) ?> <?= e($cur) ?></strong>
        <?php if ($statement['overdue'] > 0): ?> · <span class="text-danger">Vencido <strong><?= number_format($statement['overdue'], 2) ?></strong></span><?php endif ?>
        <br>Apoderados: <?= $student->guardians->map(fn ($g) => e($g->name) . ' (' . e($g->phone ?: 'sin celular') . ')')->implode(', ') ?: '<em>ninguno</em>' ?>
        <br><a href="<?= Backend::url('aero/school/students/update/' . $student->id) ?>">Ficha del estudiante</a>
    </div>
</div>

<?= Form::open() ?>
<table class="table data" style="max-width:1000px">
    <thead><tr><th style="width:30px"></th><th>Concepto</th><th>Vence</th><th style="text-align:right">Monto</th><th style="text-align:right">Pagado</th>
        <th style="text-align:right">Saldo</th><th>Estado</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($statement['charges'] as $c): $isOpen = in_array($c->status, ['pending', 'partial'], true); ?>
        <tr class="<?= $c->is_overdue ? 'text-danger' : '' ?>">
            <td><?php if ($isOpen): ?><input type="checkbox" name="charges[]" value="<?= $c->id ?>" class="ch" data-due="<?= $c->due ?>" <?= $c->is_overdue ? 'checked' : '' ?>><?php endif ?></td>
            <td><?= e($c->concept) ?></td>
            <td><?= $c->due_on?->format('d/m/Y') ?></td>
            <td style="text-align:right"><?= number_format($c->net, 2) ?></td>
            <td style="text-align:right"><?= number_format((float) $c->paid_amount, 2) ?></td>
            <td style="text-align:right"><strong><?= number_format($c->due, 2) ?></strong></td>
            <td><?= $c->status === 'paid' ? 'Pagada' : ($c->is_overdue ? 'Vencida' : ($c->status === 'partial' ? 'Parcial' : 'Pendiente')) ?></td>
            <td><?php if ($isOpen): ?><button type="button" class="btn btn-xs btn-default" data-request="onSendQr" data-request-data="charge_id: <?= $c->id ?>"
                data-request-confirm="¿Enviar el QR de esta cuota por WhatsApp al apoderado?">QR</button><?php endif ?></td>
        </tr>
    <?php endforeach ?>
    <?php if ($statement['charges']->isEmpty()): ?><tr><td colspan="8" class="text-muted">Sin cuotas. Genere las cuotas desde «Pensiones y matrícula».</td></tr><?php endif ?>
    </tbody>
</table>

<?php if ($open->isNotEmpty()): ?>
<div class="form-inline" style="margin:15px 0">
    <label>Monto a cobrar <?= e($cur) ?></label>
    <input type="number" step="0.01" min="0.01" name="amount" id="pay-amount" class="form-control" style="width:130px">
    <select name="method" class="form-control">
        <?php foreach ($methods as $k => $label): ?><option value="<?= $k ?>"><?= e($label) ?></option><?php endforeach ?>
    </select>
    <input type="text" name="reference" class="form-control" placeholder="Referencia / N° de depósito" style="width:200px">
    <select name="guardian_id" class="form-control">
        <option value="">Pagó…</option>
        <?php foreach ($student->guardians as $g): ?><option value="<?= $g->id ?>"><?= e($g->name) ?></option><?php endforeach ?>
    </select>
    <button type="button" class="btn btn-primary" data-request="onPay" data-request-loading>Registrar pago</button>
    <button type="button" class="btn btn-default" data-request="onRemind" data-request-confirm="¿Enviar recordatorio de mora por WhatsApp?">Enviar recordatorio</button>
</div>
<p class="text-muted">El pago se reparte entre las cuotas marcadas, de la más antigua a la más reciente. Para un pago parcial escriba un monto menor.</p>
<script>
(function () {
    var amount = document.getElementById('pay-amount');
    function sum() {
        var t = 0;
        document.querySelectorAll('.ch:checked').forEach(function (c) { t += parseFloat(c.dataset.due) || 0; });
        amount.value = t ? t.toFixed(2) : '';
    }
    document.querySelectorAll('.ch').forEach(function (c) { c.addEventListener('change', sum); });
    sum();
})();
</script>
<?php endif ?>
<?= Form::close() ?>

<?php if ($statement['payments']->isNotEmpty()): ?>
<h4>Historial de pagos</h4>
<table class="table data" style="max-width:800px">
    <?php foreach ($statement['payments'] as $p): ?>
    <tr><td><a href="<?= Backend::url('aero/school/payments/receipt/' . $p->id) ?>">Recibo <?= str_pad((string) $p->receipt_no, 6, '0', STR_PAD_LEFT) ?></a></td>
        <td><?= $p->paid_at?->format('d/m/Y H:i') ?></td><td><?= e(\Aero\School\Models\Payment::METHODS[$p->method] ?? $p->method) ?></td>
        <td style="text-align:right"><?= number_format((float) $p->amount, 2) ?></td></tr>
    <?php endforeach ?>
</table>
<?php endif ?>
