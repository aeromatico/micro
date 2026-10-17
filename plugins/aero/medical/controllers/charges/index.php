<?php use Aero\Medical\Models\Charge; ?>
<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/medical/charges') ?>">Cobros</a></li></ul>
<?php Block::endPut() ?>
<div class="form-preview" style="max-width:960px;margin-bottom:22px">
    <h4 style="margin-top:0">Registrar un cobro</h4>
    <?= Form::open(['data-request' => 'onCreate', 'style' => 'display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:8px;align-items:end']) ?>
        <input type="hidden" name="consultation_id" value="<?= e($pre['consultation_id']) ?>">
        <div><label style="font-size:12px">Paciente</label>
            <select name="patient_id" class="form-control" required><option value="">— Elegir —</option>
                <?php foreach ($patients as $p): ?><option value="<?= (int) $p->id ?>" <?= (int) $pre['patient_id'] === (int) $p->id ? 'selected' : '' ?>><?= e($p->name) ?></option><?php endforeach ?></select></div>
        <div><label style="font-size:12px">Concepto <span class="text-muted">(genérico)</span></label><input type="text" name="concept" class="form-control" maxlength="255" value="<?= e($pre['concept']) ?>" placeholder="Consulta, vacuna, baño…"></div>
        <div><label style="font-size:12px">Monto</label><input type="text" inputmode="decimal" name="amount" class="form-control" required value="<?= e($pre['amount']) ?>"></div>
        <div><label style="font-size:12px">Moneda</label><select name="currency" class="form-control"><option value="BOB" <?= $pre['currency'] === 'BOB' ? 'selected' : '' ?>>Bs (BOB)</option><option value="USD" <?= $pre['currency'] === 'USD' ? 'selected' : '' ?>>USD</option></select></div>
        <div><label style="font-size:12px">Forma de cobro</label>
            <select name="method" class="form-control" id="ch-method">
                <option value="cash">Efectivo (ya cobrado)</option><option value="transfer">Transferencia (ya cobrada)</option><option value="other">Otro (ya cobrado)</option>
                <?php if ($payOk && count($accounts)): ?><option value="qr">QR de Bolivia Pay (se emite)</option><?php endif ?></select></div>
        <?php if ($payOk && count($accounts)): ?>
        <div id="ch-account" style="display:none"><label style="font-size:12px">Cuenta para el QR</label>
            <select name="bank_account_id" class="form-control"><?php foreach ($accounts as $id => $n): ?><option value="<?= (int) $id ?>"><?= e($n) ?></option><?php endforeach ?></select></div>
        <?php endif ?>
        <div><button type="submit" class="btn btn-primary">Registrar</button></div>
    <?= Form::close() ?>
    <p class="text-muted" style="font-size:12px;margin:8px 0 0">El concepto lo ve recepción y viaja a Finanzas: escriba algo genérico, sin datos clínicos.
        <?= (!$payOk || !count($accounts)) ? 'Para cobrar con QR el negocio necesita Aero.Pay con una cuenta bancaria activa.' : '' ?></p>
</div>
<script>
(function () { var m = document.getElementById('ch-method'), a = document.getElementById('ch-account');
    if (m && a) { var f = function () { a.style.display = m.value === 'qr' ? '' : 'none'; }; m.addEventListener('change', f); f(); } })();
</script>

<form method="get" style="margin-bottom:10px;display:flex;gap:8px;max-width:560px">
    <input type="text" name="q" value="<?= e($q) ?>" class="form-control" placeholder="Buscar por paciente…">
    <select name="status" class="form-control" style="max-width:170px" onchange="this.form.submit()"><option value="">Todos</option>
        <?php foreach (Charge::STATUSES as $k => $l): ?><option value="<?= e($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach ?></select>
</form>
<?php if (!count($charges)): ?><p class="text-muted">No hay cobros.</p><?php else: ?>
<table class="table data" style="max-width:960px">
    <thead><tr><th>Fecha</th><th>Paciente</th><th>Concepto</th><th class="text-right">Monto</th><th>Forma</th><th>Estado</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($charges as $c): ?>
        <tr><td><?= e(($c->paid_at ?? $c->created_at)->format('d/m/Y H:i')) ?></td><td><?= e($c->patient?->name) ?></td><td><?= e($c->concept) ?></td>
            <td class="text-right"><?= e(number_format((float) $c->amount, 2)) ?> <?= e($c->currency) ?></td><td><?= e($c->method_label) ?></td><td><?= e($c->status_label) ?></td>
            <td class="text-right"><a href="<?= Backend::url('aero/medical/charges/update/' . $c->id) ?>">abrir</a></td></tr>
    <?php endforeach ?>
    </tbody>
</table>
<?php endif ?>
