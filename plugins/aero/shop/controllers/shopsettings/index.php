<?php
/** @var Aero\Shop\Controllers\ShopSettings $this */
if (!empty($this->vars['noTenant'])): ?>
<div class="padded-container">
    <div class="alert alert-warning">
        <i class="icon-warning"></i>
        No se encontró un tenant asociado a tu cuenta.
    </div>
</div>
<?php return; endif;

$settingsWidget = $this->settingsWidget;
$currencies     = $this->vars['currencies'];
$allCurrencies  = $this->vars['allCurrencies'];
?>
<div class="layout-row">
    <div class="layout-cell">
        <div class="padded-container">
            <form data-request="onSave" data-request-flash>
                <?= $settingsWidget->render() ?>
                <div class="form-buttons">
                    <button type="submit" class="btn btn-primary" data-load-indicator="Guardando...">
                        <i class="icon-check"></i> Guardar configuración
                    </button>
                </div>
            </form>

            <hr>

            <h4>Monedas activas</h4>
            <p class="help-block">
                Todos los precios del catálogo se capturan en la moneda base. Las monedas adicionales
                se muestran al convertir usando la tasa de cambio configurada aquí.
            </p>

            <div id="currencies-list" style="margin-bottom:16px">
                <?= $this->makePartial('currencies_list', ['currencies' => $currencies]) ?>
            </div>

            <form data-request="onAddCurrency" data-request-update="{ '#currencies-list': true }" class="form-inline">
                <div class="form-group" style="margin-right:10px">
                    <select name="currency_id" class="form-control custom-select">
                        <?php foreach ($allCurrencies as $currency): ?>
                            <option value="<?= $currency->id ?>"><?= e($currency->code) ?> — <?= e($currency->name) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="form-group" style="margin-right:10px">
                    <input type="number" step="0.000001" name="exchange_rate" class="form-control" placeholder="Tasa de cambio" value="1" required>
                </div>
                <button type="submit" class="btn btn-default">Agregar / actualizar moneda</button>
            </form>
        </div>
    </div>
</div>
