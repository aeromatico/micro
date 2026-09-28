<?php
/** @var Aero\WpFlash\Controllers\SiteEngine $this */
if (!empty($this->vars['noTenant'])): ?>
<div class="padded-container">
    <div class="alert alert-warning">
        <i class="icon-warning"></i>
        No hay ningún tenant asociado al sitio activo. Selecciona un sitio con tenant en el selector de sitios del backend.
    </div>
</div>
<?php return; endif; ?>

<div class="padded-container">
    <?php if (empty($this->vars['planAllows'])): ?>
    <div class="alert alert-warning">
        Tu plan actual no incluye WordPress Flash. Contacta a soporte para actualizarlo.
    </div>
    <?php endif ?>

    <p>
        Elige cómo se administra el catálogo de este sitio: nuestra plataforma
        (Aero Sitio + Tienda) o <strong>WordPress Flash</strong>, un childsite
        de WordPress/WooCommerce que administramos por ti. Con WordPress Flash,
        WooCommerce siempre manda: los productos y clientes que crees ahí se
        reflejan automáticamente en tu tienda, en tiempo real.
    </p>

    <div id="wpflashStatus">
        <?= $this->makePartial('_status', ['tenant' => $this->vars['tenant'], 'site' => $this->vars['site']]) ?>
    </div>
</div>
