/* Escáner de entradas (cámara + manual). Requiere conexión: cada lectura se valida en el servidor.
 * AeroScanner.mount(root, { validate(scan, method, eventId, done), search(q, eventId, done), stats(eventId, done) }) */
(function (w) {
  'use strict';
  var PAUSE_MS = 1600, SAME_MS = 4000;

  function $(root, name) { return root.querySelector('[data-as="' + name + '"]'); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }

  function beep(ok) {
    try {
      var C = w.AudioContext || w.webkitAudioContext; if (!C) return;
      var ctx = beep.ctx || (beep.ctx = new C()), t = ctx.currentTime;
      (ok ? [[880, 0, .12]] : [[220, 0, .18], [220, .24, .18]]).forEach(function (n) {
        var o = ctx.createOscillator(), g = ctx.createGain(); o.frequency.value = n[0]; o.type = 'square';
        g.gain.value = .12; o.connect(g); g.connect(ctx.destination); o.start(t + n[1]); o.stop(t + n[1] + n[2]);
      });
    } catch (e) {}
    try { navigator.vibrate && navigator.vibrate(ok ? 80 : [120, 80, 120]); } catch (e) {}
  }

  function mount(root, api) {
    var video = $(root, 'video'), eventSel = $(root, 'event'), resultEl = $(root, 'result'), counts = $(root, 'counts'),
        camMsg = $(root, 'cammsg'), startBtn = $(root, 'start'), switchBtn = $(root, 'switch'), off = $(root, 'offline'),
        manual = $(root, 'manual'), manualGo = $(root, 'manual-go'), search = $(root, 'search'), searchGo = $(root, 'search-go'),
        found = $(root, 'found'), recent = $(root, 'recent');
    var stream = null, running = false, busy = false, last = {text: '', at: 0}, facing = 'environment', detector = null, canvas = document.createElement('canvas'), ctx2 = canvas.getContext('2d', {willReadFrequently: true});

    function eventId() { return eventSel ? eventSel.value : ''; }

    function paintCounts(c) { if (c && counts) counts.innerHTML = '<span>' + c.in + '</span> ingresaron · ' + c.issued + ' emitidas · faltan ' + c.remaining; }

    function paintRecent(list) {
      if (!recent || !list) return;
      recent.innerHTML = list.map(function (r) {
        return '<li class="' + (r.result === 'ok' ? 'ok' : 'bad') + '"><span>' + esc(r.holder || '—') + '<br><small>' + esc(r.label) + ' · ' + esc(r.by || '') + '</small></span><small>' + esc(r.at) + '</small></li>';
      }).join('');
    }

    function show(res) {
      var cls = res.allowed ? 'ok' : 'bad', t = res.ticket;
      resultEl.className = 'as-result ' + cls;
      resultEl.innerHTML = '<h2>' + esc(res.title) + '</h2><p>' + esc(res.message) + '</p>' +
        (t ? '<p class="who">' + esc(t.holder) + '</p><p>' + esc(t.type || '') + (t.seq ? ' · entrada ' + t.seq : '') + '</p>' : '') +
        (res.at ? '<p><small style="color:#fff;opacity:.8">' + esc(res.at) + '</small></p>' : '');
      paintCounts(res.counts);
      beep(res.allowed);
    }

    function offline(on, msg) { if (off) { off.style.display = on ? 'block' : 'none'; if (msg) off.textContent = msg; } }

    function validate(text, method) {
      if (busy || !text) return;
      if (!eventId()) { show({allowed: false, title: 'ELIGE UN EVENTO', message: 'Selecciona el evento antes de escanear.'}); return; }
      busy = true;
      resultEl.className = 'as-result wait'; resultEl.innerHTML = '<h2>Validando…</h2>';
      api.validate(text, method, eventId(), function (err, res) {
        busy = false;
        if (err) { offline(true, 'Sin conexión o error del servidor: la lectura NO se registró. Vuelve a escanear.'); resultEl.className = 'as-result bad'; resultEl.innerHTML = '<h2>SIN CONEXIÓN</h2><p>No se registró el ingreso. Reintenta.</p>'; beep(false); return; }
        offline(false); show(res); refreshRecent();
      });
    }

    function refreshRecent() { api.stats && api.stats(eventId(), function (err, s) { if (!err && s) { paintCounts(s.counts); paintRecent(s.recent); } }); }

    /* ---------- cámara ---------- */
    function frame() {
      if (!running) return;
      if (video.readyState >= 2 && !busy) {
        var done = function (text) {
          var now = Date.now();
          if (text && !(text === last.text && now - last.at < SAME_MS)) {
            last = {text: text, at: now};
            validate(text, 'qr');
            running && setTimeout(function () { requestAnimationFrame(frame); }, PAUSE_MS);
            return true;
          }
          return false;
        };
        if (detector) {
          detector.detect(video).then(function (codes) { if (!(codes && codes[0] && done(codes[0].rawValue))) setTimeout(function () { requestAnimationFrame(frame); }, 90); }).catch(function () { setTimeout(function () { requestAnimationFrame(frame); }, 200); });
          return;
        }
        var vw = video.videoWidth, vh = video.videoHeight, sc = Math.min(1, 640 / Math.max(vw, vh));
        canvas.width = Math.round(vw * sc); canvas.height = Math.round(vh * sc);
        ctx2.drawImage(video, 0, 0, canvas.width, canvas.height);
        var img = ctx2.getImageData(0, 0, canvas.width, canvas.height), code = w.jsQR && w.jsQR(img.data, img.width, img.height, {inversionAttempts: 'dontInvert'});
        if (code && done(code.data)) return;
      }
      setTimeout(function () { requestAnimationFrame(frame); }, 110);
    }

    function stop() {
      running = false;
      if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
      if (video) video.srcObject = null;
      if (startBtn) startBtn.textContent = 'Activar cámara';
      if (camMsg) camMsg.textContent = 'Cámara apagada';
    }

    function start() {
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) { camMsg.textContent = 'Este navegador no permite usar la cámara (se necesita HTTPS). Usa el código manual.'; return; }
      stop();
      if ('BarcodeDetector' in w) { try { detector = new w.BarcodeDetector({formats: ['qr_code']}); } catch (e) { detector = null; } }
      if (!detector && !w.jsQR) { camMsg.textContent = 'No se pudo cargar el lector de QR.'; return; }
      navigator.mediaDevices.getUserMedia({video: {facingMode: {ideal: facing}, width: {ideal: 1280}}, audio: false}).then(function (s) {
        stream = s; video.srcObject = s; video.setAttribute('playsinline', 'true'); return video.play();
      }).then(function () {
        running = true; startBtn.textContent = 'Apagar cámara'; camMsg.textContent = 'Apunta al código QR de la entrada'; requestAnimationFrame(frame);
      }).catch(function (e) { camMsg.textContent = (e && e.name === 'NotAllowedError') ? 'Permite el acceso a la cámara en tu navegador.' : 'No se pudo abrir la cámara.'; });
    }

    startBtn && startBtn.addEventListener('click', function () { running ? stop() : start(); });
    switchBtn && switchBtn.addEventListener('click', function () { facing = facing === 'environment' ? 'user' : 'environment'; if (running) start(); });

    /* ---------- manual y búsqueda ---------- */
    function manualGoFn() { var v = manual.value.trim(); if (v) { validate(v, 'code'); manual.value = ''; } }
    manualGo && manualGo.addEventListener('click', manualGoFn);
    manual && manual.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); manualGoFn(); } });   // lector USB: termina con Enter

    function doSearch() {
      var q = search.value.trim(); if (q.length < 3) { found.innerHTML = '<li><small>Escribe al menos 3 letras.</small></li>'; return; }
      api.search(q, eventId(), function (err, list) {
        if (err) { found.innerHTML = '<li>Error de conexión</li>'; return; }
        found.innerHTML = list.length ? list.map(function (t, i) {
          return '<li><span>' + esc(t.holder) + '<br><small>' + esc(t.type || '') + ' · ' + esc(t.state) + (t.checked_in_at ? ' ' + esc(t.checked_in_at) : '') + ' · ' + esc(t.code) + '</small></span>' +
            (t.can_enter ? '<button class="as-btn" data-code="' + esc(t.code) + '">Registrar ingreso</button>' : '') + '</li>';
        }).join('') : '<li><small>Sin resultados</small></li>';
      });
    }
    searchGo && searchGo.addEventListener('click', doSearch);
    search && search.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); doSearch(); } });
    found && found.addEventListener('click', function (e) { var b = e.target.closest('button[data-code]'); if (b) { validate(b.getAttribute('data-code'), 'manual'); found.innerHTML = ''; } });

    if (eventSel) { eventSel.addEventListener('change', function () { try { localStorage.setItem('aero-scan-event', eventSel.value); } catch (e) {} refreshRecent(); }); try { var s = localStorage.getItem('aero-scan-event'); if (s && eventSel.querySelector('option[value="' + s + '"]')) eventSel.value = s; } catch (e) {} }
    w.addEventListener('online', function () { offline(false); });
    w.addEventListener('offline', function () { offline(true, 'Sin internet: el escáner necesita conexión para validar.'); });
    document.addEventListener('visibilitychange', function () { if (document.hidden) stop(); });
    refreshRecent();
    return {stop: stop, start: start};
  }

  w.AeroScanner = {mount: mount};
})(window);
