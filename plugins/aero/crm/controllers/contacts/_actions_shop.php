<div class="padded-container">
    <h4>Tienda</h4>

    <?php if (!class_exists(\Aero\Shop\Models\Customer::class)): ?>
        <p class="help-block">El plugin Aero.Shop no está instalado.</p>
    <?php elseif ($model->shop_customer_id): ?>
        <p class="help-block">Este contacto ya es cliente de tienda.</p>
    <?php else: ?>
        <p class="help-block">Este contacto todavía no es cliente de tienda.</p>
        <button
            type="button"
            class="btn btn-default"
            data-request="onConvertToShopCustomer"
            data-request-data="record_id: <?= $model->id ?>"
            data-request-confirm="¿Convertir este contacto en cliente de tienda?"
            data-request-flash>
            <i class="icon-shopping-cart"></i> Convertir en cliente de tienda
        </button>
    <?php endif; ?>
</div>
