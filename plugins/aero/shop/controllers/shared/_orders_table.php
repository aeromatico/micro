<?php /** @var \Illuminate\Support\Collection $orders */
$labels = ['pending' => 'Pendiente', 'awaiting_payment' => 'Esperando pago', 'paid' => 'Pagado', 'fulfilled' => 'Entregado', 'cancelled' => 'Cancelado', 'refunded' => 'Reembolsado'];
?>
<table class="table data" style="width:100%;background:#fff">
    <thead><tr>
        <th>Fecha</th><th>Pedido</th><th>Cliente</th><th>Estado</th>
        <th style="text-align:right">Total</th><th style="text-align:right">Comisión</th><th style="text-align:right">Neto</th>
    </tr></thead>
    <tbody>
    <?php foreach ($orders as $o): ?>
        <tr>
            <td><?= e($o->created_at?->format('d/m/Y H:i')) ?></td>
            <td><a href="<?= Backend::url('aero/shop/orders/update/' . $o->id) ?>"><?= e($o->order_number) ?></a></td>
            <td><?= e($o->customer?->email ?: $o->customer?->phone) ?></td>
            <td><?= e($labels[$o->status] ?? $o->status) ?></td>
            <td style="text-align:right"><?= number_format($o->grand_total, 2) ?></td>
            <td style="text-align:right"><?= number_format($o->commission_amount, 2) ?></td>
            <td style="text-align:right"><?= number_format($o->seller_net, 2) ?></td>
        </tr>
    <?php endforeach ?>
    <?php if (!count($orders)): ?>
        <tr><td colspan="7" style="text-align:center;color:#6b7280;padding:24px">Sin pedidos en este periodo.</td></tr>
    <?php endif ?>
    </tbody>
</table>
