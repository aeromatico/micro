<!doctype html>
<!--guide __META__ -->
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Guía: __TITLE__</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap">
<style>
:root { --bg:#eceff2; --surface:#fff; --surface-2:#f5f7f9; --ink:#1d2933; --ink-2:#55636f; --line:#d5dce2; --accent:#1f6f8b; --accent-ink:#fff; --accent-soft:#dcecf2; --hint:#b4620a; --hint-soft:#fdf0dc; --ok:#1e7a4c; --ok-soft:#dff2e8; --err:#b3261e; --err-soft:#fbe4e2;
  --g-surface:#dcecf2; --g-surface-2:#cbe1ea; --g-line:#a9c9d6; --g-ink:#10303c; --g-ink-2:#3a5c6b; --g-accent:#1a6480; --g-accent-ink:#fff; --g-accent-soft:#f4fafc;
  --font-body:"IBM Plex Sans",system-ui,-apple-system,"Segoe UI",sans-serif; --font-mono:"IBM Plex Mono",ui-monospace,Menlo,monospace; }
@media (prefers-color-scheme: dark) { :root:not([data-theme="light"]) {
  --bg:#12181d; --surface:#1a232a; --surface-2:#212c34; --ink:#e6ecf0; --ink-2:#93a3af; --line:#2f3d47; --accent:#5fb4d0; --accent-ink:#0c1a21; --accent-soft:#1b3541; --hint:#f0a94e; --hint-soft:#3a2a12; --ok:#62c592; --ok-soft:#17342a; --err:#f28b82; --err-soft:#3b1d1b; color-scheme:dark;
  --g-surface:#143443; --g-surface-2:#1c4658; --g-line:#2d5a70; --g-ink:#e8f3f8; --g-ink-2:#a3c2d1; --g-accent:#7cc4de; --g-accent-ink:#08222d; --g-accent-soft:#25566f; } }
:root[data-theme="dark"] {
  --bg:#12181d; --surface:#1a232a; --surface-2:#212c34; --ink:#e6ecf0; --ink-2:#93a3af; --line:#2f3d47; --accent:#5fb4d0; --accent-ink:#0c1a21; --accent-soft:#1b3541; --hint:#f0a94e; --hint-soft:#3a2a12; --ok:#62c592; --ok-soft:#17342a; --err:#f28b82; --err-soft:#3b1d1b; color-scheme:dark;
  --g-surface:#143443; --g-surface-2:#1c4658; --g-line:#2d5a70; --g-ink:#e8f3f8; --g-ink-2:#a3c2d1; --g-accent:#7cc4de; --g-accent-ink:#08222d; --g-accent-soft:#25566f; }
* { box-sizing: border-box; }
body { background:var(--bg); color:var(--ink); font-family:var(--font-body); font-size:14px; line-height:1.5; margin:0; padding-inline:16px; padding-block:20px 48px; }
.wrap { max-width:1760px; margin-inline:auto; display:grid; gap:20px; grid-template-columns:minmax(0,1fr); grid-template-areas:"head" "guide" "form"; }
@media (min-width:940px) { .wrap { grid-template-columns:360px minmax(0,1fr); grid-template-areas:"head head" "guide form"; align-items:start; } }
header { grid-area:head; display:flex; flex-wrap:wrap; gap:8px 16px; align-items:baseline; justify-content:space-between; }
h1 { font-size:20px; font-weight:600; margin:0; text-wrap:balance; }
.crumb { font-family:var(--font-mono); font-size:12px; color:var(--ink-2); overflow-wrap:anywhere; }
.badge { font-size:11px; letter-spacing:.06em; text-transform:uppercase; font-weight:600; padding:3px 8px; border-radius:3px; background:var(--hint-soft); color:var(--hint); }
.guide { grid-area:guide; background:var(--surface); border:1px solid var(--line); border-radius:6px; min-width:0;
  --surface:var(--g-surface); --surface-2:var(--g-surface-2); --line:var(--g-line); --ink:var(--g-ink); --ink-2:var(--g-ink-2); --accent:var(--g-accent); --accent-ink:var(--g-accent-ink); --accent-soft:var(--g-accent-soft); color:var(--ink); }
@media (min-width:940px) { .guide { position:sticky; top:12px; max-height:calc(100vh - 24px); overflow:auto; } }
.g-head { padding:14px 16px 10px; border-bottom:1px solid var(--line); }
.g-eyebrow { font-size:15px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--accent); }
.g-nav { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:12px; }
.g-nav .btn { min-height:44px; padding:10px 14px; font-size:15px; font-weight:600; border-radius:6px; border:2px solid var(--accent); }
.g-nav #gPrev { background:var(--accent-soft); color:var(--accent); }
.g-nav #gPrev:hover:not(:disabled) { background:var(--accent); color:var(--accent-ink); }
.g-nav #gNext { background:var(--accent); color:var(--accent-ink); }
.g-nav .btn:disabled { opacity:.4; cursor:not-allowed; }
.g-title { font-size:16px; font-weight:600; margin:14px 0 0; text-wrap:balance; }
.progress { height:4px; background:var(--surface-2); border-radius:2px; margin-top:10px; overflow:hidden; }
.progress > i { display:block; height:100%; background:var(--accent); width:0; transition:width .25s; }
.g-body { padding:14px 16px; display:grid; gap:12px; }
.g-body p { margin:0; }
.g-sub { font-size:11px; letter-spacing:.06em; text-transform:uppercase; font-weight:600; color:var(--ink-2); margin-bottom:2px; }
.note { padding:10px 12px; border-radius:4px; background:var(--hint-soft); color:var(--ink); }
.note b { color:var(--hint); }
.example { font-family:var(--font-mono); font-size:12px; background:var(--surface-2); border:1px solid var(--line); border-radius:4px; padding:8px 10px; white-space:pre-wrap; overflow-wrap:anywhere; }
.g-actions { display:flex; gap:8px; flex-wrap:wrap; padding:0 16px 14px; }
button { font:inherit; cursor:pointer; }
.btn { border:1px solid var(--line); background:var(--surface); color:var(--ink); padding:6px 12px; border-radius:4px; }
.btn:hover { background:var(--surface-2); }
.btn.primary { background:var(--accent); color:var(--accent-ink); border-color:var(--accent); font-weight:500; }
.btn.ghost { border-color:transparent; color:var(--accent); background:transparent; padding-inline:6px; }
.btn:focus-visible, input:focus-visible, select:focus-visible, textarea:focus-visible, .steps button:focus-visible { outline:2px solid var(--accent); outline-offset:2px; }
.steps { list-style:none; margin:0; padding:8px; border-top:1px solid var(--line); display:grid; gap:2px; }
.steps button { width:100%; text-align:left; display:grid; grid-template-columns:22px minmax(0,1fr); gap:8px; align-items:center; background:transparent; border:0; border-radius:4px; padding:6px 8px; color:var(--ink-2); }
.steps button:hover { background:var(--surface-2); }
.steps button[aria-current="step"] { background:var(--accent-soft); color:var(--ink); font-weight:500; }
.dot { width:18px; height:18px; border-radius:50%; border:1.5px solid var(--line); display:grid; place-items:center; font-size:10px; font-weight:600; }
.steps .done .dot { background:var(--ok); border-color:var(--ok); color:var(--surface); }
.steps [aria-current="step"] .dot { border-color:var(--accent); color:var(--accent); }
.formcol { grid-area:form; min-width:0; display:grid; gap:12px; }
.card { background:var(--surface); border:1px solid var(--line); border-radius:6px; min-width:0; }
.ctx { font-family:var(--font-mono); font-size:11px; color:var(--ink-2); padding:8px 16px 0; }
.panel { padding:16px; display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px 20px; align-items:start; }
@media (max-width:640px) { .panel { grid-template-columns:minmax(0,1fr); } }
.field { min-width:0; padding:6px; margin:-6px; border-radius:6px; transition:box-shadow .15s, background .15s; }
.field.full { grid-column:1/-1; }
.field.is-guided { box-shadow:0 0 0 2px var(--hint); background:var(--hint-soft); }
.field[hidden] { display:none !important; }
label.lbl { display:block; font-weight:600; font-size:13px; margin-bottom:4px; }
.req { color:var(--err); margin-left:2px; }
input[type=text], input[type=number], input[type=datetime-local], select, textarea { width:100%; font:inherit; color:var(--ink); background:var(--surface); border:1px solid var(--line); border-radius:4px; padding:7px 9px; }
textarea { resize:vertical; min-height:84px; }
input.err, select.err, textarea.err { border-color:var(--err); }
.help { font-size:12px; color:var(--ink-2); margin-top:4px; overflow-wrap:anywhere; }
.errmsg { font-size:12px; color:var(--err); margin-top:4px; }
.sw { display:flex; gap:8px; align-items:center; padding:6px 0; }
.sw input { width:18px; height:18px; }
.checks { display:grid; gap:4px; border:1px solid var(--line); border-radius:4px; padding:8px 10px; background:var(--surface); }
.checks label { display:flex; gap:8px; align-items:center; }
.rep { display:grid; gap:8px; margin-bottom:8px; }
.rep-row { display:grid; grid-template-columns:minmax(0,1.4fr) minmax(0,1fr) minmax(0,1fr) auto; gap:8px; align-items:end; border:1px solid var(--line); border-radius:4px; padding:8px; }
@media (max-width:640px) { .rep-row { grid-template-columns:minmax(0,1fr); } }
.rep-row small { display:block; font-size:11px; color:var(--ink-2); }
.trig { font-family:var(--font-mono); font-size:11px; color:var(--accent); background:var(--accent-soft); border-radius:3px; padding:1px 6px; margin-left:6px; font-weight:400; }
body:not(.show-tech) .trig { display:none; }
.toolbar { display:flex; gap:8px; flex-wrap:wrap; padding:12px 16px; border-top:1px solid var(--line); background:var(--surface-2); border-radius:0 0 6px 6px; align-items:center; }
.toolbar .spacer { flex:1; }
.chk { display:inline-flex; gap:6px; align-items:center; color:var(--ink-2); font-size:12px; }
.locked { color:var(--ink-2); font-size:12px; padding:10px 12px; background:var(--surface-2); border:1px dashed var(--line); border-radius:4px; grid-column:1/-1; }
.sample { font-size:11px; color:var(--ink-2); font-style:italic; }
#flash { position:fixed; left:50%; transform:translateX(-50%); bottom:16px; max-width:calc(100vw - 32px); padding:10px 16px; border-radius:4px; background:var(--ink); color:var(--bg); box-shadow:0 6px 24px rgba(0,0,0,.25); z-index:10; }
#flash.err { background:var(--err); color:#fff; }
@media (prefers-reduced-motion: reduce) { * { transition:none !important; } }
</style>
</head><body>
<div class="wrap">
  <header>
    <div><h1 id="h1"></h1><div class="crumb" id="crumb"></div></div>
    <span class="badge">Demo: no guarda nada real</span>
  </header>
  <aside class="guide" aria-label="Guía paso a paso">
    <div class="g-head">
      <div class="g-eyebrow" id="gEyebrow"></div>
      <div class="g-nav">
        <button class="btn" id="gPrev" type="button">← Anterior</button>
        <button class="btn primary" id="gNext" type="button">Siguiente →</button>
      </div>
      <h2 class="g-title" id="gTitle"></h2>
      <div class="progress"><i id="gBar"></i></div>
    </div>
    <div class="g-body" id="gBody"></div>
    <div class="g-actions"><button class="btn ghost" id="gFill" type="button">Rellenar ejemplo</button></div>
    <ol class="steps" id="gSteps"></ol>
  </aside>
  <main class="formcol">
    <div class="card">
      <div class="ctx" id="ctxLine"></div>
      <div class="panel" id="panel"></div>
      <div class="toolbar">
        <button class="btn primary" type="button" id="btnSave">Crear</button>
        <button class="btn" type="button" id="btnSaveClose">Crear y cerrar</button>
        <button class="btn" type="button" id="btnReset">Reiniciar demo</button>
        <span class="spacer"></span>
        <label class="chk"><input type="checkbox" id="chkTech"> Mostrar reglas técnicas (trigger)</label>
      </div>
    </div>
    <div class="sample" id="sampleNote"></div>
  </main>
</div>
<div id="flash" hidden role="status"></div>
<script>
(function () {
  var CFG = __CFG__;
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var DAYS = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
  var state = { mode: 'create', step: 0 };
  var F = CFG.fields;

  function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; }
  function byId(id) { return F.filter(function (f) { return f.id === id; })[0]; }

  function build() {
    var p = $('#panel'); p.innerHTML = '';
    F.forEach(function (f) {
      var box = el('div', 'field' + (f.type === 'textarea' || f.type === 'checks' || f.type === 'repeater' || f.type === 'switch' && f.full ? ' full' : ''));
      box.setAttribute('data-f', f.id);
      if (f.type === 'switch') {
        var sw = el('label', 'sw'); var c = document.createElement('input'); c.type = 'checkbox'; c.id = f.id; c.checked = !!f.def;
        sw.appendChild(c); var t = el('span', null, f.label); t.style.fontWeight = '600'; sw.appendChild(t); box.appendChild(sw);
      } else {
        var l = el('label', 'lbl', f.label); l.htmlFor = f.id;
        if (f.req) l.appendChild(el('span', 'req', '*'));
        if (f.show) l.appendChild(el('span', 'trig', 'show si ' + f.show + ' vacío'));
        box.appendChild(l);
        var inp;
        if (f.type === 'select') {
          inp = document.createElement('select');
          if (f.empty) { var o0 = el('option', null, f.empty); o0.value = ''; inp.appendChild(o0); }
          f.opts.forEach(function (o) { var oo = el('option', null, o[1]); oo.value = o[0]; inp.appendChild(oo); });
          if (f.def != null) inp.value = f.def;
        } else if (f.type === 'textarea') {
          inp = document.createElement('textarea');
        } else if (f.type === 'checks') {
          inp = el('div', 'checks'); inp.id = f.id;
          var qs = el('button', 'btn ghost', 'Marcar / desmarcar todos'); qs.type = 'button';
          qs.onclick = function () { var cs = inp.querySelectorAll('input'); var all = Array.prototype.every.call(cs, function (x) { return x.checked; }); Array.prototype.forEach.call(cs, function (x) { x.checked = !all; }); render(); };
          inp.appendChild(qs);
          f.opts.forEach(function (o, i) {
            var lb = el('label'); var cb = document.createElement('input'); cb.type = 'checkbox'; cb.value = o; cb.id = f.id + '_' + i;
            lb.appendChild(cb); lb.appendChild(el('span', null, o)); inp.appendChild(lb);
          });
        } else if (f.type === 'repeater') {
          inp = el('div', 'rep'); inp.id = f.id;
        } else {
          inp = document.createElement('input');
          inp.type = f.type === 'number' ? 'number' : f.type === 'datetime' ? 'datetime-local' : 'text';
          if (f.step) inp.step = f.step;
          if (f.def != null) inp.value = f.def;
          if (f.ph) inp.placeholder = f.ph;
        }
        if (f.type !== 'checks' && f.type !== 'repeater') inp.id = f.id;
        box.appendChild(inp);
        if (f.type === 'repeater') {
          var add = el('button', 'btn', '+ ' + (f.prompt || 'Agregar')); add.type = 'button'; add.id = f.id + '_add';
          add.onclick = function () { addRow(f); render(); };
          box.appendChild(add);
        }
      }
      if (f.help) box.appendChild(el('div', 'help', f.help));
      if (f.type === 'checks' || (f.type === 'select' && f.dyn)) box.appendChild(el('div', 'help', 'Opciones de ejemplo: en el panel real salen de tus propios registros.'));
      var em = el('div', 'errmsg'); em.hidden = true; box.appendChild(em);
      p.appendChild(box);
    });
    var lk = el('div', 'locked'); lk.id = 'lock'; p.appendChild(lk);
    Array.prototype.forEach.call(p.querySelectorAll('input,select,textarea'), function (e) {
      e.addEventListener('input', function () { clearErr(e); render(); });
      e.addEventListener('change', function () { render(); });
      e.addEventListener('focus', function () {
        var b = e.closest('.field'); if (!b) return;
        var i = steps().map(function (s) { return s.f.id; }).indexOf(b.getAttribute('data-f'));
        if (i >= 0 && i !== state.step) { state.step = i; render(); }
      });
    });
  }
  function addRow(f, vals) {
    var host = $('#' + f.id); var row = el('div', 'rep-row');
    var d = el('div'); d.appendChild(el('small', null, 'Día')); var sel = document.createElement('select'); sel.className = 'r-day';
    DAYS.forEach(function (n, i) { var o = el('option', null, n); o.value = String(i + 1); sel.appendChild(o); }); d.appendChild(sel); row.appendChild(d);
    var a = el('div'); a.appendChild(el('small', null, 'Desde')); var s = document.createElement('input'); s.type = 'text'; s.placeholder = '09:00'; s.className = 'r-from'; a.appendChild(s); row.appendChild(a);
    var b = el('div'); b.appendChild(el('small', null, 'Hasta')); var e = document.createElement('input'); e.type = 'text'; e.placeholder = '13:00'; e.className = 'r-to'; b.appendChild(e); row.appendChild(b);
    var rm = el('button', 'btn', 'Quitar'); rm.type = 'button'; rm.onclick = function () { row.remove(); render(); }; row.appendChild(rm);
    if (vals) { sel.value = String(vals[0]); s.value = vals[1]; e.value = vals[2]; }
    [sel, s, e].forEach(function (x) { x.addEventListener('input', render); x.addEventListener('change', render); });
    host.appendChild(row);
  }
  function rows(f) { return Array.prototype.map.call($('#' + f.id).querySelectorAll('.rep-row'), function (r) {
    return [r.querySelector('.r-day').value, r.querySelector('.r-from').value.trim(), r.querySelector('.r-to').value.trim()]; }); }

  function val(f) {
    if (f.type === 'switch') return $('#' + f.id).checked;
    if (f.type === 'checks') return Array.prototype.filter.call($('#' + f.id).querySelectorAll('input'), function (c) { return c.checked; }).length;
    if (f.type === 'repeater') return rows(f).length;
    return ($('#' + f.id).value || '').trim();
  }
  function visible(f) {
    if (state.mode === 'update' && f.ctx === 'create') return false;
    if (f.show) { var o = byId(f.show); if (o && val(o) !== '') return false; }
    return true;
  }
  function setVal(f, v) {
    if (f.type === 'switch') { $('#' + f.id).checked = !!v; return; }
    if (f.type === 'checks') { Array.prototype.forEach.call($('#' + f.id).querySelectorAll('input'), function (c, i) { c.checked = v.indexOf(i) >= 0; }); return; }
    if (f.type === 'repeater') { $('#' + f.id).innerHTML = ''; v.forEach(function (r) { addRow(f, r); }); return; }
    $('#' + f.id).value = v;
  }
  function problems() {
    var errs = [];
    F.forEach(function (f) {
      if (!visible(f)) return;
      var v = val(f);
      if (f.req && (v === '' || v === 0)) { errs.push([f.id, 'El campo ' + f.label + ' es obligatorio.']); return; }
      if (f.max && typeof v === 'string' && v.length > f.max) errs.push([f.id, f.label + ' no puede superar ' + f.max + ' caracteres.']);
      if (f.email && v && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(v)) errs.push([f.id, 'Escribe un correo válido.']);
      if (f.type === 'number' && v !== '') {
        var n = parseFloat(v);
        if (isNaN(n)) errs.push([f.id, f.label + ' debe ser un número.']);
        else if (f.min != null && n < f.min) errs.push([f.id, f.label + ' debe ser al menos ' + f.min + '.']);
        else if (f.maxn != null && n > f.maxn) errs.push([f.id, f.label + ' no puede superar ' + f.maxn + '.']);
      }
      if (f.after && v) { var o = byId(f.after); var ov = o && val(o); if (ov && v <= ov) errs.push([f.id, f.label + ' debe ser posterior a «' + o.label + '».']); }
      if (f.type === 'repeater') rows(f).forEach(function (r) {
        var ok = /^([01]?\d|2[0-3]):[0-5]\d$/;
        if (!ok.test(r[1]) || !ok.test(r[2])) errs.push([f.id, 'Cada turno necesita hora «Desde» y «Hasta» con formato HH:MM.']);
        else if (r[1] >= r[2]) errs.push([f.id, 'En cada turno, «Hasta» debe ser posterior a «Desde».']);
      });
    });
    return errs;
  }
  function showErr(id, msg) {
    var b = $('[data-f="' + id + '"]'); if (!b) return;
    var m = b.querySelector('.errmsg'); m.hidden = !msg; m.textContent = msg || '';
    var i = b.querySelector('input,select,textarea'); if (i && i.type !== 'checkbox') i.classList.toggle('err', !!msg);
  }
  function clearErr(e) { var b = e.closest('.field'); if (b) showErr(b.getAttribute('data-f'), ''); }

  function steps() {
    var s = F.filter(function (f) { return visible(f) && f.s; }).map(function (f) { return { f: f }; });
    s.push({ f: { id: '__save', s: { t: 'Guardar', b: CFG.saveBody, tip: CFG.saveTip }, ex: '' }, last: true });
    return s;
  }
  function done(st) {
    if (st.last) return state.mode === 'update';
    var v = val(st.f);
    return st.f.type === 'switch' || (v !== '' && v !== 0);
  }
  function exText(f) {
    if (f.ex == null || f.ex === '') return '';
    if (f.type === 'repeater') return f.ex.map(function (r) { return DAYS[r[0] - 1] + ' ' + r[1] + '–' + r[2]; }).join('\n');
    if (f.type === 'checks') return f.ex.map(function (i) { return f.opts[i]; }).join(', ');
    if (f.type === 'select') { var o = f.opts.filter(function (x) { return x[0] === f.ex; })[0]; return o ? o[1] : String(f.ex); }
    if (f.type === 'switch') return f.ex ? 'Activado' : 'Desactivado';
    return String(f.ex).replace('T', ' ');
  }
  function render() {
    var st = steps(); state.step = Math.max(0, Math.min(st.length - 1, state.step));
    var cur = st[state.step], f = cur.f;
    $('#gEyebrow').textContent = 'Paso ' + (state.step + 1) + ' de ' + st.length + (cur.last ? '' : f.req ? ' · obligatorio' : ' · opcional');
    $('#gTitle').textContent = f.s.t;
    var body = $('#gBody'); body.innerHTML = '';
    body.appendChild(el('p', null, f.s.b));
    var ex = exText(f);
    if (ex) { var w = el('div'); w.appendChild(el('div', 'g-sub', 'Ejemplo')); w.appendChild(el('div', 'example', ex)); body.appendChild(w); }
    if (f.s.tip) { var n = el('div', 'note'); n.appendChild(el('b', null, 'Ojo. ')); n.appendChild(document.createTextNode(f.s.tip)); body.appendChild(n); }
    $('#gBar').style.width = Math.round(100 * st.filter(done).length / st.length) + '%';
    $('#gPrev').disabled = state.step === 0; $('#gNext').disabled = state.step === st.length - 1;
    $('#gFill').hidden = cur.last ? state.mode === 'update' : f.ex == null || f.ex === '';
    $('#gFill').textContent = cur.last ? 'Crear con los datos actuales' : 'Rellenar ejemplo';
    var ol = $('#gSteps'); ol.innerHTML = '';
    st.forEach(function (s, i) {
      var li = el('li'), b = el('button'); b.type = 'button';
      if (i === state.step) b.setAttribute('aria-current', 'step');
      var d = done(s); if (d) b.className = 'done';
      b.appendChild(el('span', 'dot', d ? '✓' : String(i + 1)));
      b.appendChild(el('span', null, s.f.s.t));
      b.onclick = function () { go(i); }; li.appendChild(b); ol.appendChild(li);
    });
    F.forEach(function (x) {
      var box = $('[data-f="' + x.id + '"]'); box.hidden = !visible(x);
      box.classList.toggle('is-guided', x.id === f.id && !box.hidden);
    });
    var lk = $('#lock'); var msg = state.mode === 'update' ? CFG.lockUpdate : CFG.lock;
    lk.hidden = !msg; lk.textContent = msg || '';
  }
  function go(i, scroll) {
    state.step = i; render();
    if (scroll !== false) { var b = $('[data-f="' + steps()[state.step].f.id + '"]'); if (b && !b.hidden) b.scrollIntoView({ block: 'center', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' }); }
  }

  var ft;
  function flash(msg, isErr) { var f = $('#flash'); f.textContent = msg; f.className = isErr ? 'err' : ''; f.hidden = false; clearTimeout(ft); ft = setTimeout(function () { f.hidden = true; }, 4200); }
  function save(close) {
    F.forEach(function (f) { showErr(f.id, ''); });
    var errs = problems();
    if (errs.length) {
      errs.forEach(function (e) { showErr(e[0], e[1]); });
      var i = steps().map(function (s) { return s.f.id; }).indexOf(errs[0][0]); if (i >= 0) go(i);
      flash('No se pudo guardar: ' + errs[0][1], true); return;
    }
    state.mode = 'update';
    $('#h1').textContent = CFG.updTitle; $('#crumb').textContent = CFG.crumbUpd + ' · Guía de capacitación';
    $('#btnSave').textContent = 'Guardar'; $('#btnSaveClose').textContent = 'Guardar y cerrar';
    $('#ctxLine').textContent = 'context: update';
    render();
    flash(close ? CFG.doneClose : CFG.doneMsg);
  }

  $('#gPrev').onclick = function () { go(state.step - 1); };
  $('#gNext').onclick = function () { go(state.step + 1); };
  $('#gFill').onclick = function () {
    var cur = steps()[state.step];
    if (cur.last) { save(false); return; }
    setVal(cur.f, cur.f.ex); render();
  };
  $('#btnSave').onclick = function () { save(false); };
  $('#btnSaveClose').onclick = function () { save(true); };
  $('#btnReset').onclick = function () { location.reload(); };
  $('#chkTech').onchange = function (e) { document.body.classList.toggle('show-tech', e.target.checked); };

  $('#h1').textContent = CFG.title; $('#crumb').textContent = CFG.crumb + ' · Guía de capacitación';
  $('#ctxLine').textContent = 'context: create';
  $('#sampleNote').textContent = 'Todos los datos de esta guía son de ejemplo; la demo no guarda nada.';
  build();
  render();
})();
</script>
</body></html>
