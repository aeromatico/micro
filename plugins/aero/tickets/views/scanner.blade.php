<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<meta name="referrer" content="no-referrer">
<title>Escáner — {{ $scanner->name }}</title>
<link rel="stylesheet" href="/plugins/aero/tickets/assets/css/scanner.css?v=1">
<style>body{margin:0;background:#f3f4f7;padding:12px}</style>
</head>
<body>
<div class="as" id="scanner">
  <div class="as-off" data-as="offline"></div>
  <div class="as-bar"><strong>{{ $scanner->name }}</strong><span class="as-counts" data-as="counts"></span></div>
  @if($events->isEmpty())
    <p>No tienes eventos activos asignados. Pide al organizador que te asigne uno.</p>
  @else
  <select data-as="event" aria-label="Evento">@foreach($events as $e)<option value="{{ $e->id }}">{{ $e->name }} — {{ $e->starts_at?->format('d/m H:i') }}</option>@endforeach</select>
  <div class="as-cam" style="margin-top:10px"><video data-as="video" muted playsinline></video><div class="as-frame"></div><div class="as-msg" data-as="cammsg">Cámara apagada</div></div>
  <div class="as-row"><button class="as-btn" data-as="start">Activar cámara</button><button class="as-btn sec" data-as="switch" title="Cambiar de cámara">Girar</button></div>
  <div class="as-result wait" data-as="result"><h2>Listo para escanear</h2><p>Activa la cámara y apunta al QR.</p></div>
  <div class="as-row"><input type="text" data-as="manual" placeholder="Código de la entrada" autocomplete="off" autocapitalize="characters"><button class="as-btn sec" data-as="manual-go">Validar</button></div>
  <div class="as-row"><input type="search" data-as="search" placeholder="Buscar por nombre, correo o teléfono" autocomplete="off"><button class="as-btn sec" data-as="search-go">Buscar</button></div>
  <ul class="as-found" data-as="found"></ul>
  <h4 style="margin:14px 0 4px">Últimas lecturas</h4>
  <ul class="as-recent" data-as="recent"></ul>
  @endif
</div>
<script src="/plugins/aero/tickets/assets/js/jsQR.js?v=1"></script>
<script src="/plugins/aero/tickets/assets/js/scanner.js?v=1"></script>
<script>
(function(){
  var base = '/escaner/{{ $token }}';
  function call(path, opts, done) {
    fetch(base + path, Object.assign({headers: {'Accept': 'application/json', 'Content-Type': 'application/json'}, credentials: 'same-origin', cache: 'no-store'}, opts))
      .then(function (r) {
        if (r.status === 404 || r.status === 403) { return done(null, {allowed: false, title: 'ESCÁNER NO AUTORIZADO', message: 'El organizador desactivó este enlace o el evento no te corresponde.'}); }
        return r.json().then(function (j) { if (r.status >= 400 && !j.title) { j = {allowed: false, title: 'ACCESO DENEGADO', message: j.message || 'Solicitud no válida.'}; } done(null, j, r.status); });
      })
      .catch(function (e) { done(e || true); });
  }
  AeroScanner.mount(document.getElementById('scanner'), {
    validate: function (scan, method, eventId, done) { call('/validar', {method: 'POST', body: JSON.stringify({scan: scan, method: method, event_id: eventId})}, function (err, j) { done(err, j); }); },
    search: function (q, eventId, done) { call('/buscar?event_id=' + encodeURIComponent(eventId) + '&q=' + encodeURIComponent(q), {}, function (err, j) { done(err, Array.isArray(j) ? j : []); }); },
    stats: function (eventId, done) { call('/estado?event_id=' + encodeURIComponent(eventId), {}, function (err, j) { done(err, j); }); }
  });
})();
</script>
</body>
</html>
