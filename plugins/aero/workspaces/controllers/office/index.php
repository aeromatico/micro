<?php /** @var \Aero\Workspaces\Controllers\Office $this */ ?>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&family=Pixelify+Sans:wght@500;600&display=swap">
<?php if (!$hasTenant): ?>
    <p class="callout callout-warning no-subheader"><span class="header"><i class="icon-warning"></i> Elige el sitio de un cliente (selector de sitios) para ver su oficina.</span></p>
<?php else: ?>
<div class="ws-office">
<main class="app">
  <header class="top">
    <div>
      <h1>Oficina de Agentes</h1>
      <p id="subtitle">Tu equipo de agentes de IA trabaja a la vista.</p>
    </div>
    <div class="stats" aria-live="polite">
      <div class="stat"><b id="pts">—</b><span id="ptslabel">Puntos</span></div>
      <div class="stat"><b id="active">0</b><span>Trabajando</span></div>
    </div>
  </header>

  <section class="card chat" aria-label="Chat con el equipo">
    <div class="chat-tabs" id="chat-tabs" role="tablist" aria-label="Con quién hablar"></div>
    <ul id="log"></ul>
    <ul id="log-live" hidden aria-live="polite"></ul>
    <form class="compose" id="form">
      <div class="ideas" id="ideas"></div>
      <div class="compose-box">
        <textarea id="prompt" maxlength="4000" rows="2" placeholder="Cuéntale qué quieres lograr" aria-label="Mensaje para el equipo"></textarea>
        <button class="send" id="send" type="submit">Enviar</button>
      </div>
      <div class="compose-row">
        <span class="est" id="est">Estimado: —</span>
        <span class="hint">Enter envía · Shift+Enter salto de línea</span>
      </div>
    </form>
  </section>

  <aside class="side">
    <section class="card team">
      <h2>Equipo</h2>
      <ul id="team"></ul>
    </section>
    <details class="card stage" id="stage-box" open>
      <summary><span>Oficina</span><span class="chip" id="phase">En espera</span><button type="button" class="fs-btn" id="stage-fs" title="Pantalla completa" aria-label="Ver la oficina en pantalla completa">⛶</button></summary>
      <div class="stage-body"><canvas id="office" aria-label="Vista aérea de la oficina con tu equipo" role="img"></canvas></div>
      <p class="stage-foot">Toca un agente para seguirlo. Los escritorios vacíos se llenan al contratar más personal en el Mercado.</p>
    </details>
    <section class="card history">
      <h2>Encargos recientes</h2>
      <ul id="history"></ul>
    </section>
  </aside>
</main>
</div>
<script>
window.WS = <?= json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
// Los scripts del plugin cargan en el <head> (o después, en la navegación del backend): se espera a que existan.
(function boot(n) {
    if (typeof window.WsOfficeInit === 'function') { window.WsOfficeInit(); }
    else if (n < 100) { setTimeout(function () { boot(n + 1); }, 50); }
})(0);
</script>
<?php endif ?>
