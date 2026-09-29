<?php if ($record->status === 'pending'): ?>
    <a href="javascript:;"
        data-request="onFulfill"
        data-request-data="id: <?= $record->id ?>"
        data-request-confirm="¿Marcar como entregado?"
        data-request-success="$(this).closest('tr').fadeOut()"
        class="text-success">Entregar</a>
    &nbsp;·&nbsp;
    <a href="javascript:;"
        data-request="onCancelPurchase"
        data-request-data="id: <?= $record->id ?>"
        data-request-confirm="¿Cancelar y reembolsar esta compra?"
        data-request-success="$(this).closest('tr').fadeOut()"
        class="text-danger">Cancelar</a>
<?php endif ?>
