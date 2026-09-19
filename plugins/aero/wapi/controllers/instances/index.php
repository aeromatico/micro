<?php
/** @var \Aero\Wapi\Controllers\Instances $this */
$accounts = $this->vars['accounts'];
$profiles = $this->vars['profiles'];
$isConfigured = $this->vars['isConfigured'];
?>
<div class="layout-row">
    <div class="layout-cell padded-container">

        <?php if (!$isConfigured): ?>
        <div class="alert alert-warning">
            <i class="icon-warning"></i>
            Falta configurar la API key de wapi en
            <a href="<?= Backend::url('system/settings/update/aero/wapi/settings') ?>">Configuración → wapi</a>
            antes de poder conectar un número.
        </div>
        <?php endif ?>

        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="icon-qrcode"></i> Conectar un número nuevo</h3>
            </div>
            <div class="panel-body" id="wapi-connect-area">
                <?= $this->makePartial('connect_form', ['profiles' => $profiles]) ?>
            </div>
        </div>

        <h4>Números conectados</h4>
        <div id="wapi-account-list">
            <?= $this->makePartial('list', ['accounts' => $accounts]) ?>
        </div>

    </div>
</div>
