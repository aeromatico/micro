<?php /** @var \Aero\Workspaces\Controllers\Market $this */ ?>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&family=Pixelify+Sans:wght@500;600;700&display=swap">
<?php if (!$hasTenant): ?>
    <p class="callout callout-warning no-subheader"><span class="header"><i class="icon-warning"></i> Elige el sitio de un cliente (selector de sitios) para ver su mercado y su equipo.</span></p>
<?php else: ?>
<div class="ws-market">
    <div class="wrap">
        <nav class="nav" aria-label="Secciones">
            <span class="brand">Mercado de Agentes</span>
            <div class="tabs" role="tablist" id="tabs"></div>
            <span class="pts" id="pts" title="Puntos disponibles"></span>
        </nav>
        <main id="view"></main>
    </div>
    <div class="toast" id="toast" hidden role="status"></div>
</div>
<script>
window.WS = <?= json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
// Los scripts del plugin cargan en el <head> (o después, en la navegación del backend): se espera a que existan.
(function boot(n) {
    if (typeof window.WsMarketInit === 'function') { window.WsMarketInit(); }
    else if (n < 100) { setTimeout(function () { boot(n + 1); }, 50); }
})(0);
</script>
<?php endif ?>
