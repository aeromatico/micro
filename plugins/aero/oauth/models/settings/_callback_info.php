<?php use Aero\Oauth\Classes\Providers\ProviderRegistry; ?>
<div class="callout callout-info">
    <div class="header"><h3>Configuración en Google Cloud</h3></div>
    <div class="content">
        <p>Crea un <em>ID de cliente OAuth (aplicación web)</em> y registra esta URI de redirección autorizada:</p>
        <p><code><?= e(ProviderRegistry::callbackUrl('google')) ?></code></p>
        <p>Credenciales en el <code>.env</code> (no se guardan en la base de datos):
            <code>GOOGLE_CLIENT_ID</code> y <code>GOOGLE_CLIENT_SECRET</code>.
            Estado: <strong><?= ProviderRegistry::get('google')?->isConfigured() ? 'configurado' : 'FALTAN credenciales' ?></strong></p>
    </div>
</div>
