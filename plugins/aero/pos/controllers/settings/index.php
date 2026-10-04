<?php
/** @var Aero\Pos\Controllers\Settings $this */
if (!empty($this->vars['noTenant'])): ?>
<div class="padded-container">
    <div class="alert alert-warning"><i class="icon-warning"></i> No se encontró un negocio asociado a tu cuenta.</div>
</div>
<?php return; endif; ?>
<div class="layout-row">
    <div class="layout-cell">
        <div class="padded-container">
            <?php if (!$this->vars['shopEnabled']): ?>
                <div class="alert alert-warning">
                    <i class="icon-warning"></i>
                    El POS vende a través de la tienda y la tienda está desactivada.
                    Actívala en <a href="<?= Backend::url('aero/shop/shopsettings') ?>">Tienda → Configuración</a>.
                </div>
            <?php endif ?>
            <form data-request="onSave" data-request-flash>
                <?= $this->settingsWidget->render() ?>
                <div class="form-buttons">
                    <button type="submit" class="btn btn-primary" data-load-indicator="Guardando...">
                        <i class="icon-check"></i> Guardar configuración
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
