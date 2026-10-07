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

  <section class="card stage" aria-label="Oficina">
    <div class="stage-head">
      <h2>Estudio</h2>
      <span class="chip" id="phase">En espera</span>
    </div>
    <div class="stage-body"><canvas id="office" aria-label="Vista aérea de la oficina con tu equipo" role="img"></canvas></div>
    <p class="stage-foot">Toca un agente en la oficina o en la lista para seguirlo. Los escritorios vacíos se llenan al contratar más personal en el Mercado.</p>
  </section>

  <aside class="side">
    <section class="card team">
      <h2>Equipo</h2>
      <ul id="team"></ul>
    </section>
    <section class="card chat">
      <div class="chat-tabs" id="chat-tabs" role="tablist" aria-label="Con quién hablar"></div>
      <ul id="log"></ul>
      <ul id="log-live" hidden aria-live="polite"></ul>
      <form class="compose" id="form">
        <div class="ideas" id="ideas"></div>
        <textarea id="prompt" maxlength="2000" placeholder="Cuéntale qué quieres lograr" aria-label="Encargo para el orquestador"></textarea>
        <div class="compose-row">
          <span class="est" id="est">Estimado: —</span>
          <button class="send" id="send" type="submit">Enviar encargo</button>
        </div>
      </form>
    </section>
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
