<?php /** @var Aero\Shop\Controllers\Kitchen $this */ ?>
<style>
/* Pantalla de Cocina — apariencia de la caja (POS): tokens cálidos, claro y oscuro (automático o con el botón). Todo va bajo .kx para no tocar el resto del backend. La demo de la web (bin/kitchen-demo) usa este mismo CSS. */
.kx{
  --bg:#F8F3EA;--surface:#fff;--surface2:#FCF9F3;--line:#E8DFD0;--ink:#241E17;--muted:#6E6457;
  --accent:#0E7C66;--accent-ink:#fff;--accent-soft:#DDF1EA;--accent-text:#0B6552;
  --header:#0B5E4E;--header-ink:#FFF8EC;--danger:#A32A2A;--danger-soft:#FBE4E1;--warn-soft:#FBEBCB;--warn-ink:#7A4B00;
  --amber:#F2A93B;--amber-ink:#3A2600;--shadow:0 6px 16px rgba(14,124,102,.18);
  --st-new:#3F66D6;--st-new-soft:#E6ECFB;--st-prep:#C97A00;--st-prep-soft:#FBEBCB;--st-ready:#0E7C66;--st-ready-soft:#DDF1EA;
  --note:#FDEFA8;--note-ink:#6B4A00;--tabletag:#241E17;--tabletag-ink:#FFF8EC;
}
.kx[data-theme="dark"]{
  --bg:#14100C;--surface:#1F1A15;--surface2:#262019;--line:#3A3228;--ink:#F5EEE2;--muted:#B3A794;
  --accent:#2BB894;--accent-ink:#06251D;--accent-soft:#16352D;--accent-text:#6FDDBA;
  --header:#0D3A31;--danger:#FF9C93;--danger-soft:#4A2522;--warn-soft:#4A3611;--warn-ink:#F7C873;--shadow:none;
  --st-new:#8EA6FF;--st-new-soft:#232B4A;--st-prep:#F2A93B;--st-prep-soft:#4A3611;--st-ready:#2BB894;--st-ready-soft:#16352D;
  --note:#4A3C10;--note-ink:#F7DE8A;--tabletag:#F5EEE2;--tabletag-ink:#14100C;
}
@media (prefers-color-scheme:dark){
  .kx:not([data-theme="light"]){
    --bg:#14100C;--surface:#1F1A15;--surface2:#262019;--line:#3A3228;--ink:#F5EEE2;--muted:#B3A794;
    --accent:#2BB894;--accent-ink:#06251D;--accent-soft:#16352D;--accent-text:#6FDDBA;
    --header:#0D3A31;--danger:#FF9C93;--danger-soft:#4A2522;--warn-soft:#4A3611;--warn-ink:#F7C873;--shadow:none;
    --st-new:#8EA6FF;--st-new-soft:#232B4A;--st-prep:#F2A93B;--st-prep-soft:#4A3611;--st-ready:#2BB894;--st-ready-soft:#16352D;
    --note:#4A3C10;--note-ink:#F7DE8A;--tabletag:#F5EEE2;--tabletag-ink:#14100C;
  }
}
.kx,.kx *{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
.kx{color:var(--ink);font-size:15px;line-height:1.4}
:where(.kx button){font:inherit;color:inherit;cursor:pointer;touch-action:manipulation}
.kx button:focus-visible{outline:2px solid var(--amber);outline-offset:2px}
:where(.kx a){color:inherit}
.kx-ic{flex:none;vertical-align:-3px}

/* Barra superior (como la de la caja) */
.kx-head{display:flex;flex-wrap:wrap;align-items:center;gap:10px;padding:10px 16px;margin-bottom:14px;background:var(--header);color:var(--header-ink);border-radius:16px}
.kx-title{margin:0;font-size:18px;font-weight:800;display:flex;align-items:center;gap:8px;color:var(--header-ink)}
.kx-live{display:inline-flex;align-items:center;gap:6px;font-size:12px;opacity:.9}
.kx-live.is-off .kx-dot{background:var(--amber);animation:none}
.kx-dot{width:8px;height:8px;border-radius:50%;background:#5BE3B8;box-shadow:0 0 0 0 rgba(91,227,184,.6);animation:kx-ping 2s infinite}
.kx-spacer{flex:1}
.kx-head .ibtn{height:40px;min-width:40px;padding:0 12px;border-radius:12px;border:1px solid rgba(255,255,255,.3);background:transparent;color:var(--header-ink);font-size:13px;font-weight:600;display:inline-flex;align-items:center;justify-content:center;gap:6px}
.kx-head .ibtn:hover{background:rgba(255,255,255,.12)}
.kx-toggle{display:inline-flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;user-select:none}
.kx-toggle input{accent-color:var(--amber);width:16px;height:16px}
.kx[data-theme="dark"] .kx-th-moon,.kx:not([data-theme="dark"]) .kx-th-sun{display:none}

.kx-wrap{background:var(--bg);border-radius:18px;padding:14px 16px 24px}
.kx:fullscreen .kx-wrap,.kx-wrap:fullscreen{border-radius:0;min-height:100vh;padding:18px;overflow:auto}
.kx-bar{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-bottom:14px}
.kx-chip,.kx-btn{border:1px solid var(--line);background:var(--surface);color:var(--ink);border-radius:22px;padding:0 16px;height:44px;font-size:14px;font-weight:600;display:inline-flex;align-items:center;gap:8px}
.kx-chip b{background:var(--surface2);border:1px solid var(--line);border-radius:999px;padding:0 8px;font-size:12px;line-height:20px}
#kitchen-board[data-filter="all"] .kx-chip[data-f="all"],#kitchen-board[data-filter="dine_in"] .kx-chip[data-f="dine_in"],
#kitchen-board[data-filter="pickup"] .kx-chip[data-f="pickup"],#kitchen-board[data-filter="delivery"] .kx-chip[data-f="delivery"]{background:var(--accent);color:var(--accent-ink);border-color:var(--accent)}
#kitchen-board[data-filter="all"] .kx-chip[data-f="all"] b,#kitchen-board[data-filter="dine_in"] .kx-chip[data-f="dine_in"] b,
#kitchen-board[data-filter="pickup"] .kx-chip[data-f="pickup"] b,#kitchen-board[data-filter="delivery"] .kx-chip[data-f="delivery"] b{background:rgba(255,255,255,.22);border-color:transparent;color:inherit}
#kitchen-board[data-filter="dine_in"] .k-card:not([data-type="dine_in"]),#kitchen-board[data-filter="pickup"] .k-card:not([data-type="pickup"]),#kitchen-board[data-filter="delivery"] .k-card:not([data-type="delivery"]){display:none}
.kx-busy.is-on{background:var(--warn-soft);border-color:var(--amber);color:var(--warn-ink)}

/* Columnas */
.kx-cols{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,330px),1fr));gap:14px;align-items:start}
.kx-col{background:var(--surface2);border:1px solid var(--line);border-radius:18px;padding:10px;min-height:220px}
.kx-colhead{display:flex;align-items:center;justify-content:space-between;padding:8px 12px;margin-bottom:10px;border-radius:12px;background:var(--surface);border:1px solid var(--line);border-top:4px solid var(--c);font-weight:800}
.kx-colhead .n{background:var(--c);color:#fff;border-radius:999px;padding:1px 11px;font-size:13px}
.kx[data-theme="dark"] .kx-colhead .n{color:#14100C}
@media (prefers-color-scheme:dark){.kx:not([data-theme="light"]) .kx-colhead .n{color:#14100C}}

/* Tarjeta de pedido */
.k-card{--c:var(--st-new);background:var(--surface);border:1px solid var(--line);border-left:6px solid var(--c);border-radius:16px;padding:14px;margin-bottom:12px;box-shadow:var(--shadow)}
.k-card[data-st="preparing"]{--c:var(--st-prep)}
.k-card[data-st="ready"]{--c:var(--st-ready)}
.k-card.is-new{animation:kx-flash 1.6s ease-out 2}
.k-top{display:flex;align-items:center;justify-content:space-between;gap:8px}
.k-num{font-size:26px;font-weight:800;letter-spacing:.2px;line-height:1}
.k-num small{font-size:11px;font-weight:600;color:var(--muted);letter-spacing:.02em;margin-left:4px}
.k-timer{font-variant-numeric:tabular-nums;font-weight:800;font-size:14px;padding:3px 10px;border-radius:999px;background:var(--accent-soft);color:var(--accent-text)}
.k-timer.warn{background:var(--warn-soft);color:var(--warn-ink)}
.k-timer.late{background:var(--danger-soft);color:var(--danger);animation:kx-pulse 1.2s infinite}
.k-meta{display:flex;flex-wrap:wrap;gap:6px;margin:8px 0}
.k-pill{display:inline-flex;align-items:center;gap:5px;border-radius:10px;padding:4px 10px;font-size:13px;font-weight:700;background:var(--st-new-soft);color:var(--st-new)}
.k-pill.pickup{background:#EFE6FB;color:#6B3FB5}
.k-pill.delivery{background:#FBE4D6;color:#9A4417}
.kx[data-theme="dark"] .k-pill.pickup{background:#2E2A52;color:#B8AEFF}
.kx[data-theme="dark"] .k-pill.delivery{background:#46261A;color:#F5A57C}
@media (prefers-color-scheme:dark){.kx:not([data-theme="light"]) .k-pill.pickup{background:#2E2A52;color:#B8AEFF}.kx:not([data-theme="light"]) .k-pill.delivery{background:#46261A;color:#F5A57C}}
.k-pill.table{background:var(--tabletag);color:var(--tabletag-ink);font-size:15px}
.k-sched{background:var(--warn-soft);border:1px dashed var(--amber);color:var(--warn-ink);border-radius:10px;padding:6px 10px;font-weight:700;font-size:13px;margin-bottom:8px}
.k-channel{display:flex;flex-wrap:wrap;gap:6px;margin:2px 0 6px;font-size:12px;font-weight:700}
.k-src{padding:2px 8px;border-radius:8px;background:var(--st-new-soft);color:var(--st-new)}
.k-src-pos{background:var(--accent-soft);color:var(--accent-text)}
.k-waiter{padding:2px 8px;border-radius:8px;background:var(--surface2);border:1px solid var(--line);color:var(--muted)}
.k-cust{font-size:13px;color:var(--muted)}
.k-cust a{text-decoration:underline dotted}
.k-items{list-style:none;margin:10px 0;padding:0}
.k-items li{display:grid;grid-template-columns:36px 1fr;gap:10px;padding:9px 0;border-top:1px solid var(--line)}
.k-items li.k-round{display:block;padding:6px 0 2px;border-top:2px solid var(--line);font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:var(--muted)}
.k-items li.k-round.is-latest{color:var(--st-new)}
.k-qty{width:34px;height:34px;border-radius:10px;background:var(--ink);color:var(--surface);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:16px}
.k-name{font-size:16px;font-weight:700;line-height:1.25}
.k-extras{display:flex;flex-wrap:wrap;gap:4px;margin-top:4px}
.k-extra{background:var(--surface2);border:1px solid var(--line);border-radius:8px;padding:1px 8px;font-size:12.5px;font-weight:600}
.k-note{margin-top:5px;background:var(--note);border-radius:8px;padding:4px 8px;font-size:13px;font-weight:700;color:var(--note-ink)}
.k-onote{background:var(--warn-soft);border-radius:10px;padding:7px 10px;font-size:13px;font-weight:600;color:var(--warn-ink);margin-bottom:10px}
.kx-chip .kx-ic,.kx-btn .kx-ic,.k-pill .kx-ic,.k-cust .kx-ic,.k-sched .kx-ic,.k-note .kx-ic,.k-onote .kx-ic{margin-right:2px}
.k-go .kx-ic{margin-left:4px}.k-undo .kx-ic,.k-step .kx-ic{margin:0}

/* Tiempo estimado */
.k-eta{display:grid;gap:8px;background:var(--st-new-soft);border:1px solid var(--line);border-radius:14px;padding:10px 12px;margin:8px 0 10px}
.k-eta.k-eta-set{background:var(--st-prep-soft)}
.k-eta.is-late{background:var(--danger-soft);color:var(--danger)}
.k-eta-label{font-size:13px;font-weight:600;display:flex;flex-wrap:wrap;align-items:center;gap:6px}
.k-eta-label small{flex-basis:100%;font-weight:500;color:var(--muted)}
.k-eta-label small.k-left{flex-basis:auto;font-weight:700}
.k-eta-ctl{display:flex;align-items:center;justify-content:center;gap:8px;flex-wrap:wrap}
.k-eta-val{font-size:22px;min-width:84px;text-align:center;font-variant-numeric:tabular-nums;line-height:1}
.k-eta-cap{font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.04em}
.k-eta-hint{font-size:12px;color:var(--muted);text-align:center}
.k-step{min-height:44px;min-width:44px;padding:0 12px;border:1px solid var(--line);background:var(--surface);color:var(--st-new);border-radius:999px;font-weight:800;font-size:14px;display:inline-flex;align-items:center;justify-content:center;gap:4px}
.k-step:hover:not(:disabled){border-color:var(--st-new)}
.k-step:active:not(:disabled){transform:scale(.94)}
.k-step:disabled{opacity:.35;cursor:not-allowed}
.k-eta-set .k-eta-cap{flex-basis:100%;text-align:left}
.k-eta-set .k-step.k-plus{flex:1;border-radius:12px;color:var(--warn-ink)}
.k-eta.is-late .k-step.k-plus{color:var(--danger)}
.k-step.k-reset{color:var(--muted);visibility:hidden}
.k-eta.is-adjusted .k-step.k-reset{visibility:visible}

/* Acciones */
.k-actions{display:flex;gap:8px;margin-top:6px}
.k-go{flex:1;min-height:50px;border:0;border-radius:14px;font-size:16px;font-weight:800;color:#fff;background:var(--c);box-shadow:0 6px 14px rgba(0,0,0,.12)}
.kx[data-theme="dark"] .k-go{color:#14100C;box-shadow:none}
@media (prefers-color-scheme:dark){.kx:not([data-theme="light"]) .k-go{color:#14100C;box-shadow:none}}
.k-go:hover{filter:brightness(1.07)}.k-go:active{transform:scale(.98)}
.k-undo{min-height:50px;min-width:52px;padding:0 14px;border:1px solid var(--line);background:var(--surface);color:var(--ink);border-radius:14px;display:inline-flex;align-items:center;justify-content:center}
.k-undo:hover{background:var(--surface2)}
.k-empty{text-align:center;color:var(--muted);padding:34px 10px;font-size:14px}
.k-empty .kx-ic{display:block;width:34px;height:34px;margin:0 auto 6px;opacity:.45}

/* Entregados */
.kx-panics{display:flex;flex-direction:column;gap:6px;margin-bottom:12px}
.kx-panic{display:flex;flex-wrap:wrap;align-items:center;gap:10px;padding:8px 12px;border-radius:14px;background:var(--danger-soft);color:var(--danger);border:1px solid var(--danger);border-left-width:6px}
.kx-panic b{font-size:16px}
.kx-panic-meta{font-size:12px;opacity:.85;flex:1;min-width:140px}
.kx-panic-dot{width:10px;height:10px;border-radius:50%;background:var(--danger);animation:kx-panic 2.2s ease-in-out infinite}
.kx-panic-ack{height:36px!important;padding:0 12px!important;font-size:13px!important}
@keyframes kx-panic{0%,100%{opacity:1}50%{opacity:.35}}
.kx-done{margin-top:18px}
.kx-done h5{margin:0 0 8px;color:var(--muted);font-size:13px;text-transform:uppercase;letter-spacing:.06em}
.kx-done span{display:inline-flex;gap:8px;align-items:center;background:var(--surface);border:1px solid var(--line);border-radius:999px;padding:6px 14px;margin:0 6px 6px 0;font-size:13px;font-weight:600}
.kx-done a{text-decoration:none;color:var(--muted);display:inline-flex}

@keyframes kx-ping{70%{box-shadow:0 0 0 8px rgba(91,227,184,0)}100%{box-shadow:0 0 0 0 rgba(91,227,184,0)}}
@keyframes kx-flash{0%,100%{transform:none}25%{box-shadow:0 0 0 4px var(--st-new),0 8px 24px rgba(63,102,214,.3);transform:scale(1.012)}}
@keyframes kx-pulse{50%{opacity:.55}}
@media (prefers-reduced-motion:reduce){*,*::before{animation:none!important;transition:none!important}}
@media (max-width:640px){
  .kx-head{padding:8px 10px;gap:8px}.kx-head .hide-xs{display:none}
  .kx-wrap{padding:10px 10px 20px}
  .kx-chip,.kx-btn{height:40px;padding:0 12px;font-size:13px}
  .k-go{min-height:54px}
}
</style>

<div class="padded-container kx">
<?php if (!$isRestaurant): ?>
    <div class="alert alert-warning"><?= \Aero\Shop\Classes\KitchenIcons::svg('utensils') ?>
        La pantalla de cocina está disponible cuando el tipo de tienda es «Restaurante» (Tienda → Configuración).
    </div>
<?php else: ?>
    <div class="kx-wrap" id="kitchen-wrap">
        <div class="kx-bar kx-head">
            <h3 class="kx-title"><?= \Aero\Shop\Classes\KitchenIcons::svg('utensils') ?> Cocina</h3>
            <span class="kx-live" id="kx-live"><i class="kx-dot"></i><span id="kx-updated">En vivo</span></span>
            <span class="kx-spacer"></span>
            <label class="kx-toggle"><input type="checkbox" id="kitchen-sound" checked> <?= \Aero\Shop\Classes\KitchenIcons::svg('bell') ?> Sonido</label>
            <button type="button" class="kx-btn ibtn" id="kx-test" title="Probar sonido"><?= \Aero\Shop\Classes\KitchenIcons::svg('play') ?> Probar</button>
            <button type="button" class="kx-btn ibtn" id="kx-full" title="Pantalla completa"><?= \Aero\Shop\Classes\KitchenIcons::svg('maximize') ?> Pantalla completa</button>
            <button type="button" class="kx-btn ibtn" id="kx-theme" title="Modo claro u oscuro" aria-label="Cambiar entre modo claro y oscuro">
                <span class="kx-th-moon"><?= \Aero\Shop\Classes\KitchenIcons::svg('moon') ?></span><span class="kx-th-sun"><?= \Aero\Shop\Classes\KitchenIcons::svg('sun') ?></span>
            </button>
        </div>
        <div id="kitchen-board" data-filter="all"><?= $board ?></div>
    </div>

    <script>
    (function () {
        var board = document.getElementById('kitchen-board');
        var wrap = document.getElementById('kitchen-wrap');
        var known = null, knownPanics = null, offset = 0, failing = false, adj = {}, lastStep = 0, MIN = 5, MAX = 240;

        function beep() {
            try {
                var c = new (window.AudioContext || window.webkitAudioContext)();
                [880, 1175].forEach(function (f, i) {
                    var o = c.createOscillator(), g = c.createGain();
                    o.connect(g); g.connect(c.destination); o.frequency.value = f; g.gain.value = 0.18;
                    o.start(c.currentTime + i * 0.22); o.stop(c.currentTime + i * 0.22 + 0.18);
                });
                setTimeout(function () { c.close(); }, 700);
            } catch (e) {}
        }

        function readIds() {
            var el = board.querySelector('[data-new-ids]');
            try { return el ? JSON.parse(el.getAttribute('data-new-ids')) : []; } catch (e) { return []; }
        }

        function tick() {
            var now = Math.floor(Date.now() / 1000) + offset;
            board.querySelectorAll('.k-eta-set[data-due]').forEach(function (box) {
                var diff = Math.round((parseInt(box.getAttribute('data-due'), 10) - now) / 60), el = box.querySelector('.k-left');
                if (el) el.textContent = diff >= 0 ? '· faltan ' + diff + ' min' : '· retrasado ' + Math.abs(diff) + ' min';
                box.classList.toggle('is-late', diff < 0);
            });
            board.querySelectorAll('.k-timer').forEach(function (t) {
                var m = Math.max(0, Math.floor((now - parseInt(t.getAttribute('data-ts'), 10)) / 60));
                t.textContent = m < 60 ? m + ' min' : Math.floor(m / 60) + ' h ' + (m % 60) + ' min';
                var limit = parseInt(t.getAttribute('data-limit') || '20', 10);
                t.classList.toggle('warn', m >= limit * 0.6 && m < limit);
                t.classList.toggle('late', m >= limit);
            });
        }

        function etaVal(box) { return Math.min(MAX, Math.max(MIN, parseInt(box.getAttribute('data-base'), 10) + (adj[box.getAttribute('data-id')] || 0))); }

        function paintEta() {
            board.querySelectorAll('.k-eta[data-base]').forEach(function (box) {
                var id = box.getAttribute('data-id'), base = parseInt(box.getAttribute('data-base'), 10);
                // El ajuste nunca sale del rango: así cada clic siempre mueve el valor.
                adj[id] = Math.min(MAX - base, Math.max(MIN - base, adj[id] || 0));
                var m = etaVal(box);
                box.querySelector('.k-eta-val').textContent = m + ' min';
                box.classList.toggle('is-adjusted', adj[id] !== 0);
                box.querySelector('[data-step="-5"]').disabled = m <= MIN;
                box.querySelector('[data-step="5"]').disabled = m >= MAX;
                var at = box.querySelector('.k-start-at');
                if (at) {
                    var start = new Date((parseInt(at.getAttribute('data-sched'), 10) - m * 60) * 1000);
                    at.textContent = start.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                }
            });
        }

        function onBoard() {
            var meta = board.querySelector('[data-server-ts]');
            if (meta) offset = parseInt(meta.getAttribute('data-server-ts'), 10) - Math.floor(Date.now() / 1000);
            var ids = readIds();
            if (known !== null) {
                var fresh = ids.filter(function (id) { return known.indexOf(id) === -1; });
                fresh.forEach(function (id) { var c = board.querySelector('.k-card[data-id="' + id + '"]'); if (c) c.classList.add('is-new'); });
                if (fresh.length && document.getElementById('kitchen-sound').checked) beep();
            }
            known = ids;
            // Emergencia nueva: un solo tono suave (el aviso fijo queda en la banda de arriba hasta que se atienda).
            var pEl = board.querySelector('[data-panic-ids]'), pIds = [];
            try { pIds = pEl ? JSON.parse(pEl.getAttribute('data-panic-ids')) : []; } catch (e) {}
            if (knownPanics !== null && pIds.some(function (id) { return knownPanics.indexOf(id) === -1; }) && document.getElementById('kitchen-sound').checked) beep();
            knownPanics = pIds;
            paintEta();
            tick();
            document.title = (ids.length ? '(' + ids.length + ') ' : '') + 'Cocina';
        }

        function refresh() {
            // No se refresca mientras se ajusta un tiempo: evita que el valor salte.
            if (document.hidden || Date.now() - lastStep < 6000) return;
            $.request('onRefresh', {
                complete: function () {
                    onBoard();
                    failing = false;
                    document.getElementById('kx-live').classList.remove('is-off');
                    document.getElementById('kx-updated').textContent = 'En vivo · ' + new Date().toLocaleTimeString();
                },
                error: function () {
                    failing = true;
                    document.getElementById('kx-live').classList.add('is-off');
                    document.getElementById('kx-updated').textContent = 'Sin conexión, reintentando…';
                }
            });
        }

        board.addEventListener('click', function (e) {
            var chip = e.target.closest('.kx-chip');
            if (chip) board.setAttribute('data-filter', chip.getAttribute('data-f'));

            var step = e.target.closest('.k-eta[data-base] .k-step');
            if (step && !step.disabled) {
                var box = step.closest('.k-eta'), id = box.getAttribute('data-id');
                if (step.hasAttribute('data-reset')) adj[id] = 0;
                else adj[id] = (adj[id] || 0) + parseInt(step.getAttribute('data-step'), 10);
                lastStep = Date.now();
                paintEta();
            }

            var start = e.target.closest('.k-start');
            if (start) {
                var b = board.querySelector('.k-eta[data-id="' + start.getAttribute('data-id') + '"]');
                $.request('onMove', { data: { order_id: start.getAttribute('data-id'), to: 'preparing', eta: b ? etaVal(b) : 0 } });
                delete adj[start.getAttribute('data-id')];
            }
        });
        // Tras mover un pedido el servidor ya devuelve el tablero: solo actualizamos estado local.
        $(document).on('ajaxComplete', function () { if (document.getElementById('kitchen-board')) onBoard(); });

        // Modo claro/oscuro: lo recuerda este navegador; sin elección, sigue al sistema.
        var kx = document.querySelector('.kx');
        function setTheme(t) { kx.setAttribute('data-theme', t); try { localStorage.setItem('aero.kx.theme', t); } catch (e) {} }
        (function () {
            var saved = null;
            try { saved = localStorage.getItem('aero.kx.theme'); } catch (e) {}
            kx.setAttribute('data-theme', saved || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'));
        })();
        document.getElementById('kx-theme').addEventListener('click', function () {
            setTheme(kx.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
        });

        document.getElementById('kx-test').addEventListener('click', beep);
        document.getElementById('kx-full').addEventListener('click', function () {
            if (document.fullscreenElement) document.exitFullscreen(); else wrap.requestFullscreen && wrap.requestFullscreen();
        });

        onBoard();
        setInterval(tick, 15000);
        setInterval(refresh, 8000);
        document.addEventListener('visibilitychange', function () { if (!document.hidden) refresh(); });
    })();
    </script>
<?php endif ?>
</div>
