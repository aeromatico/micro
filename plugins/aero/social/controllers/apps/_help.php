<div class="callout callout-info no-subheader" style="margin-bottom:12px">
    <div class="content">
        <p><strong>Opcional.</strong> Si no cargas llaves, «Conectar» usa la app de Market. Usa las tuyas si la red aún no está habilitada en la plataforma o si prefieres tu propia cuota (por ejemplo, el plan de la API de X).</p>
        <p>En la configuración de tu app, registra esta URL de retorno:<br><code><?= e(\Aero\Social\Classes\MetaConnect::redirectUri()) ?></code></p>
        <p>Después de guardarlas, vuelve a <a href="<?= Backend::url('aero/social/connections') ?>">Conexiones</a> y conecta (o reconecta) la cuenta.</p>
    </div>
</div>
