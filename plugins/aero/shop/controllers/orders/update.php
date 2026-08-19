<div class="form-buttons">
    <?php $order = $formModel; ?>
    <?php if (in_array($order->status, ['pending', 'awaiting_payment'])): ?>
        <button
            type="button"
            class="btn btn-success"
            data-request="onMarkAsPaid"
            data-request-data="order_id: <?= $order->id ?>"
            data-request-confirm="¿Confirmas que el pago de este pedido fue recibido?">
            <i class="icon-check"></i> Marcar como pagado
        </button>
    <?php endif ?>

    <?php if ($order->status === 'paid'): ?>
        <button
            type="button"
            class="btn btn-primary"
            data-request="onMarkAsFulfilled"
            data-request-data="order_id: <?= $order->id ?>"
            data-request-confirm="¿Confirmas que el pedido fue despachado/entregado?">
            <i class="icon-truck"></i> Marcar como despachado/entregado
        </button>
    <?php endif ?>

    <?php if (!in_array($order->status, ['fulfilled', 'cancelled', 'refunded'])): ?>
        <button
            type="button"
            class="btn btn-danger"
            data-request="onCancelOrder"
            data-request-data="order_id: <?= $order->id ?>"
            data-request-confirm="¿Confirmas cancelar este pedido? Se liberará el stock reservado si ya se había descontado.">
            <i class="icon-times"></i> Cancelar pedido
        </button>
    <?php endif ?>
</div>

<?= $this->formRenderDesign() ?>
