<?php /** @var Aero\Shop\Controllers\Kitchen $this */ ?>
<style>
.kx { --kx-new:#2563eb; --kx-prep:#d97706; --kx-ready:#16a34a; --kx-late:#dc2626; --kx-ink:#111827; --kx-mute:#6b7280; --kx-line:#e5e7eb; --kx-bg:#f3f4f6; color:var(--kx-ink); }
.kx-wrap { background:var(--kx-bg); border-radius:12px; padding:14px; }
.kx:fullscreen .kx-wrap, .kx-wrap:fullscreen { border-radius:0; min-height:100vh; padding:18px; overflow:auto; }
.kx-bar { display:flex; flex-wrap:wrap; align-items:center; gap:10px; margin-bottom:12px; }
.kx-title { font-size:20px; font-weight:800; margin:0 10px 0 0; }
.kx-live { display:inline-flex; align-items:center; gap:6px; font-size:12px; color:var(--kx-mute); }
.kx-dot { width:9px; height:9px; border-radius:50%; background:var(--kx-ready); box-shadow:0 0 0 0 rgba(22,163,74,.6); animation:kx-ping 2s infinite; }
.kx-live.is-off .kx-dot { background:var(--kx-late); animation:none; }
.kx-spacer { flex:1; }
.kx-chip, .kx-btn { border:1px solid var(--kx-line); background:#fff; color:var(--kx-ink); border-radius:999px; padding:8px 14px; font-size:13px; font-weight:600; cursor:pointer; min-height:40px; transition:background .15s, border-color .15s; }
.kx-chip:hover, .kx-btn:hover { border-color:#9ca3af; }
.kx-chip b { margin-left:6px; background:#e5e7eb; border-radius:999px; padding:1px 7px; font-size:11px; }
#kitchen-board[data-filter="all"] .kx-chip[data-f="all"],
#kitchen-board[data-filter="dine_in"] .kx-chip[data-f="dine_in"],
#kitchen-board[data-filter="pickup"] .kx-chip[data-f="pickup"],
#kitchen-board[data-filter="delivery"] .kx-chip[data-f="delivery"] { background:var(--kx-ink); color:#fff; border-color:var(--kx-ink); }
#kitchen-board[data-filter="all"] .kx-chip[data-f="all"] b,
#kitchen-board[data-filter="dine_in"] .kx-chip[data-f="dine_in"] b,
#kitchen-board[data-filter="pickup"] .kx-chip[data-f="pickup"] b,
#kitchen-board[data-filter="delivery"] .kx-chip[data-f="delivery"] b { background:rgba(255,255,255,.25); }
#kitchen-board[data-filter="dine_in"] .k-card:not([data-type="dine_in"]),
#kitchen-board[data-filter="pickup"] .k-card:not([data-type="pickup"]),
#kitchen-board[data-filter="delivery"] .k-card:not([data-type="delivery"]) { display:none; }
.kx-toggle { display:inline-flex; align-items:center; gap:6px; font-size:13px; cursor:pointer; user-select:none; }
.kx-cols { display:grid; grid-template-columns:repeat(auto-fit,minmax(min(100%,320px),1fr)); gap:14px; align-items:start; }
.kx-col { background:rgba(255,255,255,.55); border-radius:12px; padding:10px; min-height:220px; }
.kx-colhead { position:sticky; top:0; z-index:2; display:flex; align-items:center; justify-content:space-between; padding:8px 10px; margin-bottom:10px; border-radius:10px; background:#fff; border-top:4px solid var(--c); font-weight:800; }
.kx-colhead span.n { background:var(--c); color:#fff; border-radius:999px; padding:2px 11px; font-size:13px; }
.k-card { --c:var(--kx-new); background:#fff; border-radius:12px; padding:14px; margin-bottom:12px; border:1px solid var(--kx-line); border-left:6px solid var(--c); box-shadow:0 1px 3px rgba(0,0,0,.06); }
.k-card[data-st="preparing"] { --c:var(--kx-prep); }
.k-card[data-st="ready"] { --c:var(--kx-ready); }
.k-card.is-new { animation:kx-flash 1.6s ease-out 2; }
.k-top { display:flex; align-items:center; justify-content:space-between; gap:8px; }
.k-num { font-size:20px; font-weight:800; letter-spacing:.2px; }
.k-timer { font-variant-numeric:tabular-nums; font-weight:800; font-size:15px; padding:4px 10px; border-radius:999px; background:#ecfdf5; color:#047857; }
.k-timer.warn { background:#fffbeb; color:#b45309; }
.k-timer.late { background:#fef2f2; color:var(--kx-late); animation:kx-pulse 1.2s infinite; }
.k-meta { display:flex; flex-wrap:wrap; gap:6px; margin:8px 0; }
.k-pill { display:inline-flex; align-items:center; gap:5px; border-radius:8px; padding:4px 10px; font-size:13px; font-weight:700; background:#eef2ff; color:#3730a3; }
.k-pill.pickup { background:#f5f3ff; color:#6d28d9; }
.k-pill.delivery { background:#fff7ed; color:#c2410c; }
.k-pill.table { background:#111827; color:#fff; font-size:15px; }
.k-sched { background:#fffbeb; border:1px dashed #f59e0b; color:#92400e; border-radius:8px; padding:6px 10px; font-weight:700; font-size:13px; margin-bottom:8px; }
.k-cust { font-size:13px; color:var(--kx-mute); }
.k-cust a { color:inherit; text-decoration:underline dotted; }
.k-items { list-style:none; margin:10px 0; padding:0; }
.k-items li { display:grid; grid-template-columns:36px 1fr; gap:10px; padding:9px 0; border-top:1px solid var(--kx-line); }
.k-qty { width:34px; height:34px; border-radius:9px; background:var(--kx-ink); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:16px; }
.k-name { font-size:16px; font-weight:700; line-height:1.25; }
.k-extras { display:flex; flex-wrap:wrap; gap:4px; margin-top:4px; }
.k-extra { background:#f3f4f6; border-radius:6px; padding:2px 8px; font-size:12.5px; font-weight:600; }
.k-note { margin-top:5px; background:#fef08a; border-radius:6px; padding:4px 8px; font-size:13px; font-weight:700; color:#713f12; }
.k-onote { background:#fef3c7; border-radius:8px; padding:7px 10px; font-size:13px; font-weight:600; color:#78350f; margin-bottom:10px; }
.k-actions { display:flex; gap:8px; margin-top:6px; }
.k-go { flex:1; min-height:48px; border:0; border-radius:10px; font-size:16px; font-weight:800; color:#fff; background:var(--c); cursor:pointer; transition:filter .15s, transform .05s; }
.k-go:hover { filter:brightness(1.08); }
.k-go:active { transform:scale(.98); }
.k-undo { min-width:48px; min-height:48px; border:1px solid var(--kx-line); background:#fff; border-radius:10px; font-size:18px; cursor:pointer; }
.k-empty { text-align:center; color:var(--kx-mute); padding:34px 10px; font-size:14px; }
.k-empty i { display:block; font-size:30px; margin-bottom:6px; opacity:.4; font-style:normal; }
.kx i[class^="icon-"] { font-style:normal; }
.kx-chip i, .kx-btn i, .k-pill i, .k-cust i, .k-sched i, .k-note i, .k-onote i { margin-right:2px; }
.k-go i { margin-left:4px; vertical-align:-1px; }
.k-eta { display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; background:#eff6ff; border:1px solid #bfdbfe; border-radius:10px; padding:8px 10px; margin:8px 0 10px; }
.k-eta.k-eta-set { background:#fffbeb; border-color:#fde68a; }
.k-eta.is-late { background:#fef2f2; border-color:#fecaca; color:var(--kx-late); }
.k-eta-label { font-size:13px; font-weight:600; }
.k-eta-label small { display:block; font-weight:500; color:var(--kx-mute); }
.k-eta-ctl { display:inline-flex; align-items:center; gap:8px; }
.k-eta-val { font-size:17px; min-width:64px; text-align:center; font-variant-numeric:tabular-nums; }
.k-step { min-height:36px; min-width:44px; padding:0 10px; border:1px solid var(--kx-line); background:#fff; border-radius:8px; font-weight:700; cursor:pointer; }
.kx-busy.is-on { background:#fff7ed; border-color:#f97316; color:#c2410c; }
.kx-done { margin-top:16px; }
.kx-done h5 { margin:0 0 8px; color:var(--kx-mute); font-size:13px; text-transform:uppercase; letter-spacing:.06em; }
.kx-done span { display:inline-flex; gap:8px; align-items:center; background:#fff; border:1px solid var(--kx-line); border-radius:999px; padding:5px 12px; margin:0 6px 6px 0; font-size:13px; font-weight:600; }
.kx-done a { text-decoration:none; }
@keyframes kx-ping { 70% { box-shadow:0 0 0 8px rgba(22,163,74,0); } 100% { box-shadow:0 0 0 0 rgba(22,163,74,0); } }
@keyframes kx-flash { 0%,100% { box-shadow:0 1px 3px rgba(0,0,0,.06); } 25% { box-shadow:0 0 0 4px rgba(37,99,235,.55), 0 8px 24px rgba(37,99,235,.35); transform:scale(1.015); } }
@keyframes kx-pulse { 50% { opacity:.55; } }
@media (prefers-reduced-motion: reduce) { .kx *, .kx *::before { animation:none !important; transition:none !important; } }
@media (max-width:640px) { .kx-title { width:100%; } .k-go { min-height:54px; } }
</style>

<div class="padded-container kx">
<?php if (!$isRestaurant): ?>
    <div class="alert alert-warning"><i class="icon-warning"></i>
        La pantalla de cocina está disponible cuando el tipo de tienda es «Restaurante» (Tienda → Configuración).
    </div>
<?php else: ?>
    <div class="kx-wrap" id="kitchen-wrap">
        <div class="kx-bar">
            <h3 class="kx-title"><i class="icon-cutlery"></i> Cocina</h3>
            <span class="kx-live" id="kx-live"><i class="kx-dot"></i><span id="kx-updated">En vivo</span></span>
            <span class="kx-spacer"></span>
            <label class="kx-toggle"><input type="checkbox" id="kitchen-sound" checked> <i class="icon-bell"></i> Sonido</label>
            <button type="button" class="kx-btn" id="kx-test" title="Probar sonido"><i class="icon-play"></i> Probar</button>
            <button type="button" class="kx-btn" id="kx-full" title="Pantalla completa"><i class="icon-expand"></i> Pantalla completa</button>
        </div>
        <div id="kitchen-board" data-filter="all"><?= $board ?></div>
    </div>

    <script>
    (function () {
        var board = document.getElementById('kitchen-board');
        var wrap = document.getElementById('kitchen-wrap');
        var known = null, offset = 0, failing = false, adj = {};

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
            board.querySelectorAll('.k-timer').forEach(function (t) {
                var m = Math.max(0, Math.floor((now - parseInt(t.getAttribute('data-ts'), 10)) / 60));
                t.textContent = m < 60 ? m + ' min' : Math.floor(m / 60) + ' h ' + (m % 60) + ' min';
                var limit = parseInt(t.getAttribute('data-limit') || '20', 10);
                t.classList.toggle('warn', m >= limit * 0.6 && m < limit);
                t.classList.toggle('late', m >= limit);
            });
        }

        function etaVal(box) { return Math.min(240, Math.max(5, parseInt(box.getAttribute('data-base'), 10) + (adj[box.getAttribute('data-id')] || 0))); }

        function paintEta() {
            board.querySelectorAll('.k-eta[data-base]').forEach(function (box) {
                var m = etaVal(box);
                box.querySelector('.k-eta-val').textContent = m + ' min';
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
            paintEta();
            tick();
            document.title = (ids.length ? '(' + ids.length + ') ' : '') + 'Cocina';
        }

        function refresh() {
            if (document.hidden) return;
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

            var step = e.target.closest('.k-eta[data-base] .k-step[data-step]');
            if (step) {
                var box = step.closest('.k-eta'), id = box.getAttribute('data-id');
                adj[id] = (adj[id] || 0) + parseInt(step.getAttribute('data-step'), 10);
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
