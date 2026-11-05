<div class="padded-container">
<div class="as" id="scanner">
  <div class="as-off" data-as="offline"></div>
  <?php if ($events->isEmpty()): ?>
    <p class="text-muted">No hay eventos publicados o en curso para controlar el ingreso.</p>
  <?php else: ?>
  <div class="as-bar"><span class="as-counts" data-as="counts"></span>
    <a href="<?= Backend::url('aero/tickets/scans') ?>">Ver registro de accesos</a></div>
  <select data-as="event" aria-label="Evento">
    <?php foreach ($events as $e): ?><option value="<?= (int) $e->id ?>" <?= $eventId === (int) $e->id ? 'selected' : '' ?>><?= e($e->name) ?> — <?= e($e->starts_at?->format('d/m H:i')) ?></option><?php endforeach ?>
  </select>
  <div class="as-cam" style="margin-top:10px"><video data-as="video" muted playsinline></video><div class="as-frame"></div><div class="as-msg" data-as="cammsg">Cámara apagada</div></div>
  <div class="as-row"><button class="as-btn" data-as="start" type="button">Activar cámara</button><button class="as-btn sec" data-as="switch" type="button">Girar</button></div>
  <div class="as-result wait" data-as="result"><h2>Listo para escanear</h2><p>Activa la cámara, usa un lector USB o escribe el código.</p></div>
  <div class="as-row"><input type="text" data-as="manual" placeholder="Código o lectura del QR" autocomplete="off" autofocus><button class="as-btn sec" data-as="manual-go" type="button">Validar</button></div>
  <div class="as-row"><input type="search" data-as="search" placeholder="Buscar por nombre, correo, teléfono o código" autocomplete="off"><button class="as-btn sec" data-as="search-go" type="button">Buscar</button></div>
  <ul class="as-found" data-as="found"></ul>
  <h4 style="margin:14px 0 4px">Últimas lecturas</h4>
  <ul class="as-recent" data-as="recent"></ul>
  <script>
  (function(){
    function ajax(handler, data, done) {
      $.request(handler, {data: data, flash: false, success: function (r) { done(null, r); }, error: function () { done(true); }, handleErrorMessage: function () {}});
    }
    AeroScanner.mount(document.getElementById('scanner'), {
      validate: function (scan, method, eventId, done) { ajax('onScan', {scan: scan, method: method, event_id: eventId}, function (e, r) { done(e, r && r.result); }); },
      search: function (q, eventId, done) { ajax('onSearch', {q: q, event_id: eventId}, function (e, r) { done(e, (r && r.found) || []); }); },
      stats: function (eventId, done) { ajax('onStats', {event_id: eventId}, function (e, r) { done(e, r); }); }
    });
  })();
  </script>
  <?php endif ?>
</div></div>
