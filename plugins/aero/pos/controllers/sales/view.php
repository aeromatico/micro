<?php /** @var Aero\Pos\Controllers\Sales $this */
$m = fn ($n) => number_format((float) $n, 2);
$o = $sale->order;
?>
<div class="padded-container" style="max-width:860px">
    <p><a href="<?= Backend::url('aero/pos/sales') ?>"><i class="icon-arrow-left"></i> Ventas</a></p>
    <h3><?= e($o->order_number) ?> <small class="text-muted"><?= $sale->created_at->format('d/m/Y H:i') ?></small></h3>
    <p class="text-muted">
        Cajero: <?= e(trim(($sale->cashier?->first_name ?? '') . ' ' . ($sale->cashier?->last_name ?? ''))) ?: '—' ?>
        <?php if ($sale->pos_table): ?> · Mesa <?= e($sale->pos_table->name) ?><?php endif ?>
        <?php if ($sale->terminal): ?> · <?= e($sale->terminal->name) ?><?php endif ?>
        <?php if ($sale->nit): ?> · NIT <?= e($sale->nit) ?> <?= e($sale->tax_name) ?><?php endif ?>
    </p>
    <table class="table table-striped">
        <thead><tr><th>Producto</th><th>Cant.</th><th style="text-align:right">Importe</th></tr></thead>
        <tbody>
        <?php foreach ($o->items as $i): ?>
            <tr><td><?= e($i->product_name_snapshot) ?>
                <?php if ($i->modifiers): ?><br><small class="text-muted">+ <?= e(\Aero\Shop\Classes\RestaurantService::modifiersText($i->modifiers)) ?></small><?php endif ?>
                <?php if ($i->note): ?><br><small><?= e($i->note) ?></small><?php endif ?></td>
                <td><?= (int) $i->quantity ?></td><td style="text-align:right"><?= $m($i->line_total) ?></td></tr>
        <?php endforeach ?>
        </tbody>
        <tfoot>
            <tr><td colspan="2" style="text-align:right">Subtotal</td><td style="text-align:right"><?= $m($o->subtotal) ?></td></tr>
            <?php if ($o->discount_total > 0): ?><tr><td colspan="2" style="text-align:right">Descuento<?= $sale->discount_reason ? ' (' . e($sale->discount_reason) . ')' : '' ?></td><td style="text-align:right">− <?= $m($o->discount_total) ?></td></tr><?php endif ?>
            <?php if ($sale->tip_total > 0): ?><tr><td colspan="2" style="text-align:right">Propina</td><td style="text-align:right"><?= $m($sale->tip_total) ?></td></tr><?php endif ?>
            <tr><th colspan="2" style="text-align:right">Total</th><th style="text-align:right"><?= $m($sale->amountDue()) ?></th></tr>
        </tfoot>
    </table>
    <h4>Cobros</h4>
    <table class="table">
        <thead><tr><th>Método</th><th style="text-align:right">Monto</th><th style="text-align:right">Entregó</th><th style="text-align:right">Vuelto</th><th>Estado</th></tr></thead>
        <tbody>
        <?php foreach ($sale->payments as $p): ?>
            <tr><td><?= e($p->method?->label) ?><?= $p->reference ? ' · ' . e($p->reference) : '' ?></td><td style="text-align:right"><?= $m($p->amount) ?></td>
                <td style="text-align:right"><?= $p->tendered !== null ? $m($p->tendered) : '—' ?></td><td style="text-align:right"><?= $m($p->change_given) ?></td><td><?= $p->status === 'completed' ? 'Cobrado' : e($p->status) ?></td></tr>
        <?php endforeach ?>
        <?php if ($sale->payments->isEmpty()): ?><tr><td colspan="5" class="text-muted">Sin cobros: la cuenta sigue abierta.</td></tr><?php endif ?>
        </tbody>
    </table>
    <a class="btn btn-default" href="<?= Backend::url('aero/shop/orders/update/' . $o->id) ?>">Ver el pedido en Tienda</a>
</div>
