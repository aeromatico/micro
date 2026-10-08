<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/finance/pettycash') ?>">Caja chica</a></li><li><?= e($fund->name) ?></li></ul>
<?php Block::endPut() ?>
<?php $n = fn ($v) => number_format((float) $v, 2); $today = today()->toDateString(); $card = 'background:color-mix(in srgb, var(--bs-body-color) 5%, var(--bs-body-bg));border:1px solid var(--bs-border-color);border-radius:6px;padding:12px;margin-bottom:12px'; ?>

<div style="<?= $card ?>"><div style="color:var(--bs-secondary-color);font-size:12px">Saldo según el libro (Bs)</div>
    <div style="font-size:30px;font-weight:700"><?= $n($fund->balance) ?></div>
    <?php if ($fund->imprest_amount): ?><div class="text-muted">Monto fijo: <?= $n($fund->imprest_amount) ?> · para reponer: <?= $n(max(0, $fund->imprest_amount - $fund->balance)) ?></div><?php endif ?>
    <div class="text-muted">Cuenta contable: <?= e($fund->account?->label) ?></div></div>

<?php if ($fund->is_active): ?>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:12px">
    <?php
    $blocks = [
        ['onFundExpense', 'Registrar gasto', 'Sale de la caja chica y queda como egreso.', 'expense'],
        ['onFundAdd', 'Fondear / reponer', 'Entrega de efectivo desde Caja o Bancos a la caja chica.', 'source'],
        ['onFundReturn', 'Devolver', 'Devuelve efectivo de la caja chica a Caja o Bancos.', 'source'],
        ['onFundCount', 'Arqueo', 'Cuente el efectivo; la diferencia se asienta como sobrante o faltante.', 'count'],
    ];
    foreach ($blocks as [$handler, $title, $help, $mode]): ?>
    <?= Form::open(['data-request' => $handler, 'data-request-flash' => true, 'style' => $card]) ?>
        <input type="hidden" name="fund_id" value="<?= $fund->id ?>">
        <h5><?= e($title) ?></h5><p class="text-muted" style="font-size:12px"><?= e($help) ?></p>
        <input type="date" name="date" value="<?= $today ?>" class="form-control" style="margin-bottom:6px">
        <?php if ($mode === 'count'): ?>
            <input type="number" step="0.01" min="0" name="counted" placeholder="Efectivo contado (Bs)" class="form-control" style="margin-bottom:6px" required>
        <?php else: ?>
            <input type="number" step="0.01" min="0.01" name="amount" placeholder="Monto (Bs)" class="form-control" style="margin-bottom:6px" required>
        <?php endif ?>
        <?php if ($mode === 'expense'): ?>
            <select name="category_account_id" class="form-control" style="margin-bottom:6px" required>
                <?php foreach ($categories as $c): ?><option value="<?= $c->id ?>"><?= e($c->label) ?></option><?php endforeach ?>
            </select>
            <input name="counterparty" placeholder="Proveedor" class="form-control" style="margin-bottom:6px">
            <input name="document_no" placeholder="N.º de factura o recibo" class="form-control" style="margin-bottom:6px">
            <input type="number" step="0.01" min="0" name="tax_amount" placeholder="IVA incluido (opcional)" class="form-control" style="margin-bottom:6px">
        <?php elseif ($mode === 'source'): ?>
            <select name="account_id" class="form-control" style="margin-bottom:6px" required>
                <?php foreach ($sources as $a): ?><option value="<?= $a->id ?>"><?= e($a->label) ?></option><?php endforeach ?>
            </select>
        <?php endif ?>
        <input name="description" placeholder="<?= $mode === 'expense' ? 'Descripción del gasto' : 'Nota (opcional)' ?>" class="form-control" style="margin-bottom:8px" <?= $mode === 'expense' ? 'required' : '' ?>>
        <button type="submit" class="btn btn-primary"><?= e($title) ?></button>
    <?= Form::close() ?>
    <?php endforeach ?>
</div>
<?php else: ?><p class="flash-message static warning">Caja chica desactivada: solo consulta.</p><?php endif ?>

<h4>Gastos recientes</h4>
<table class="table data">
    <thead><tr><th>Fecha</th><th>Descripción</th><th style="text-align:right">Monto</th><th>Estado</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($expenses as $m): ?>
        <tr><td><?= $m->date->format('d/m/Y') ?></td><td><?= e($m->description) ?></td><td style="text-align:right"><?= $n($m->amount) ?></td><td><?= $m->status === 'void' ? 'anulado' : 'registrado' ?></td>
            <td><a href="<?= Backend::url('aero/finance/movements/update/' . $m->id) ?>">Ver / anular</a></td></tr>
    <?php endforeach ?>
    <?php if (!count($expenses)): ?><tr><td colspan="5" class="text-muted">Sin gastos.</td></tr><?php endif ?>
    </tbody>
</table>

<h4>Fondeos, devoluciones y arqueos</h4>
<table class="table data">
    <thead><tr><th>Fecha</th><th>Operación</th><th style="text-align:right">Monto</th><th style="text-align:right">Diferencia</th><th>Estado</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($operations as $o): ?>
        <tr><td><?= $o->date->format('d/m/Y') ?></td><td><?= e(\Aero\Finance\Models\PettyOperation::KINDS[$o->kind] ?? $o->kind) ?><?= $o->description ? ' — ' . e($o->description) : '' ?></td>
            <td style="text-align:right"><?= $n($o->amount) ?></td><td style="text-align:right"><?= $o->kind === 'count' ? $n($o->difference) : '' ?></td>
            <td><?= $o->status === 'void' ? 'anulada' : 'registrada' ?></td>
            <td><?php if ($o->status === 'posted' && $o->kind !== 'count'): ?>
                <a href="javascript:;" data-request="onFundVoid" data-request-data="fund_id: <?= $fund->id ?>, operation_id: <?= $o->id ?>" data-request-confirm="¿Anular esta operación?">Anular</a><?php endif ?></td></tr>
    <?php endforeach ?>
    <?php if (!count($operations)): ?><tr><td colspan="6" class="text-muted">Sin operaciones.</td></tr><?php endif ?>
    </tbody>
</table>

<h4>Datos de la caja</h4>
<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row"><?= $this->formRender() ?></div>
    <div class="form-buttons">
        <button type="submit" data-request="onSave" data-hotkey="ctrl+enter, cmd+enter" class="btn btn-default">Guardar datos</button>
        <a href="<?= Backend::url('aero/finance/pettycash') ?>" class="btn btn-default">Volver</a>
    </div>
<?= Form::close() ?>
