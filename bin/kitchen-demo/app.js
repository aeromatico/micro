/* Demo de la pantalla de Cocina (Tienda → Cocina). Misma interfaz y reglas que el backend,
   con datos ficticios de «Sabor Andino». El reloj de la demo corre 20 veces más rápido para
   ver los avisos de retraso sin esperar. Nada se envía a ningún servidor. */
(function () {
    var ICONS = __ICONS__;
    var SPEED = 20, T0 = Date.now();
    var vnow = function () { return T0 + (Date.now() - T0) * SPEED; };
    var MIN = 5, MAX = 240;
    var CFG = { capacity: 3, loadMinutes: 5, busyExtra: 10, deliveryExtra: 10, defaultPrep: 15 };
    var TYPES = { dine_in: 'Comer en local', pickup: 'Recoger', delivery: 'Delivery' };
    var TYPE_ICON = { dine_in: 'utensils', pickup: 'bag', delivery: 'bike' };
    var STATUS = { new: { label: 'Nuevos', next: 'preparing', action: 'Empezar', color: 'var(--st-new)' }, preparing: { label: 'Preparando', next: 'ready', action: 'Listo', color: 'var(--st-prep)' }, ready: { label: 'Listos', next: 'delivered', action: 'Entregado', color: 'var(--st-ready)' } };
    var PREV = { preparing: 'new', ready: 'preparing', delivered: 'ready' };
    var EMPTY = { new: ['inbox', 'Sin pedidos nuevos'], preparing: ['flame', 'Nada en preparación'], ready: ['check-circle', 'Nada esperando entrega'] };
    var SRC = { pos: 'Local (POS)', whatsapp: 'WhatsApp', chat: 'Chat' };

    function ic(n) { return '<svg class="kx-ic" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + (ICONS[n] || ICONS.utensils) + '</svg>'; }
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
    function hm(ms) { return new Date(ms).toLocaleTimeString('es-BO', { hour: '2-digit', minute: '2-digit' }); }
    function roundUp(m) { return Math.max(5, Math.ceil(m / 5) * 5); }

    var seq = 20, busy = false, known = null, adj = {}, lastStep = 0, filter = 'all', lastTick = 0;
    var ago = function (min) { return vnow() - min * 60000; };
    var I = function (qty, name, prep, extra) { return Object.assign({ qty: qty, name: name, prep: prep, round: 1, mods: [], note: '' }, extra || {}); };

    var orders = [
        { id: 18, number: 'PED-000018', type: 'dine_in', table: '2', source: 'pos', waiter: 'Carla', customer: { name: 'Cliente de mostrador', phone: '' }, status: 'preparing', createdAt: ago(11), acceptedAt: ago(10), promisedAt: vnow() + 6 * 60000, scheduled: null, onote: '',
          items: [I(2, 'Plato del día', 20, { mods: ['Extra picante'], note: 'Uno sin cebolla' }), I(2, 'Limonada', 4)] },
        { id: 19, number: 'PED-000019', type: 'pickup', table: '', source: 'web', waiter: '', customer: { name: 'Luis Fernández', phone: '70123456' }, status: 'new', createdAt: ago(3), scheduled: null, onote: 'Paso a recoger en 20 minutos.',
          items: [I(1, 'Sándwich mixto', 12, { mods: ['Queso extra'] }), I(1, 'Jugo natural', 4)] },
        { id: 20, number: 'PED-000020', type: 'delivery', table: '', source: 'whatsapp', waiter: '', customer: { name: 'María Quispe', phone: '76543210' }, status: 'new', createdAt: ago(1), scheduled: null, onote: 'Casa verde, segundo piso. Tocar el timbre.',
          items: [I(6, 'Empanada de queso', 10), I(1, 'Gaseosa 2 L', 2)] },
        { id: 17, number: 'PED-000017', type: 'dine_in', table: '5', source: 'pos', waiter: 'Carla', customer: { name: 'Cliente de mostrador', phone: '' }, status: 'ready', createdAt: ago(16), acceptedAt: ago(15), promisedAt: ago(2), readyAt: ago(1), scheduled: null, onote: '',
          items: [I(1, 'Ensalada fresca', 8, { mods: ['Aderezo aparte'] }), I(2, 'Café americano', 5)] },
        { id: 15, number: 'PED-000015', type: 'dine_in', table: '1', source: 'pos', waiter: 'Carla', customer: { name: 'Cliente de mostrador', phone: '' }, status: 'delivered', createdAt: ago(40), updatedAt: ago(22), scheduled: null, onote: '', items: [I(2, 'Torta, porción', 3)] },
        { id: 16, number: 'PED-000016', type: 'pickup', table: '', source: 'web', waiter: '', customer: { name: 'Pedro Rojas', phone: '71234567' }, status: 'delivered', createdAt: ago(35), updatedAt: ago(14), scheduled: null, onote: '', items: [I(1, 'Plato del día', 20)] }
    ];
    orders[0].items[0].round = 1; orders[0].items.push(I(1, 'Flan casero', 3, { round: 2 }));

    var TEMPLATES = [
        function () { return { type: 'dine_in', table: String(1 + Math.floor(Math.random() * 6)), source: 'pos', waiter: 'Carla', customerFn: function () { return { name: 'Cliente de mostrador', phone: '' }; }, onote: '', items: [I(2, 'Sándwich mixto', 12), I(2, 'Jugo natural', 4, { note: 'Sin hielo' })] }; },
        function () { return { type: 'dine_in', table: String(1 + Math.floor(Math.random() * 6)), source: 'pos', waiter: 'Carla', customerFn: function () { return { name: 'Cliente de mostrador', phone: '' }; }, onote: '', items: [I(1, 'Plato del día', 20, { mods: ['Extra picante'] }), I(1, 'Ensalada fresca', 8), I(2, 'Cappuccino', 5, { mods: ['Grande'] })] }; },
        function () { return { type: 'pickup', table: '', source: 'web', waiter: '', customer: { name: 'Ana Mamani', phone: '72345678' }, onote: 'Voy en moto, salgo en 15 minutos.', items: [I(4, 'Empanada de queso', 10), I(1, 'Limonada', 4)] }; },
        function () { return { type: 'delivery', table: '', source: 'whatsapp', waiter: '', customer: { name: 'Jorge Vargas', phone: '78901234' }, onote: 'Edificio Torre Sol, piso 3. Pagan con QR.', items: [I(2, 'Plato del día', 20), I(2, 'Gaseosa 2 L', 2), I(1, 'Brownie', 3)] }; },
        function () { return { type: 'pickup', table: '', source: 'web', waiter: '', customer: { name: 'Rosa Choque', phone: '73456789' }, scheduled: vnow() + 55 * 60000, onote: 'Pedido programado para el almuerzo.', items: [I(3, 'Plato del día', 20, { note: 'Uno sin ají' }), I(3, 'Jugo natural', 4)] }; }
    ];

    function eta(o) {
        var prep = Math.max.apply(null, o.items.map(function (i) { return i.prep || 0; })) || CFG.defaultPrep;
        var queue = orders.filter(function (x) { return x.id !== o.id && (x.status === 'new' || x.status === 'preparing'); }).length;
        var load = Math.max(0, queue - CFG.capacity) * CFG.loadMinutes;
        return roundUp(prep + load + (busy ? CFG.busyExtra : 0) + (o.type === 'delivery' ? CFG.deliveryExtra : 0));
    }
    function limitOf(o, st) { return st === 'ready' ? 9999 : Math.max(10, Math.max.apply(null, o.items.map(function (i) { return i.prep || 0; })) || 20); }
    function etaVal(o) { return Math.min(MAX, Math.max(MIN, eta(o) + (adj[o.id] || 0))); }

    function cardHtml(o, key, col) {
        var h = '<article class="k-card" data-id="' + o.id + '" data-st="' + key + '" data-type="' + o.type + '">';
        h += '<div class="k-top"><span class="k-num">#' + (String(o.number).match(/(\d+)\s*$/) ? parseInt(String(o.number).match(/(\d+)\s*$/)[1], 10) : esc(o.number)) + ' <small>' + esc(o.number) + '</small></span><span class="k-timer" data-ts="' + o.createdAt + '" data-limit="' + limitOf(o, key) + '">…</span></div>';
        h += '<div class="k-meta"><span class="k-pill ' + o.type + '">' + ic(TYPE_ICON[o.type]) + ' ' + esc(TYPES[o.type]) + '</span>' + (o.table ? '<span class="k-pill table">Mesa ' + esc(o.table) + '</span>' : '') + '</div>';
        if (o.scheduled) h += '<div class="k-sched">' + ic('clock') + ' Programado para ' + new Date(o.scheduled).toLocaleString('es-BO', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) + '</div>';
        if (o.source !== 'web' || o.waiter) h += '<div class="k-channel"><span class="k-src k-src-' + o.source + '">' + esc(SRC[o.source] || o.source) + '</span>' + (o.waiter ? '<span class="k-waiter">' + ic('user') + ' ' + esc(o.waiter) + '</span>' : '') + '</div>';
        h += '<div class="k-cust">' + ic('user') + ' ' + esc(o.customer.name) + (o.customer.phone ? ' · <a href="tel:' + esc(o.customer.phone) + '">' + esc(o.customer.phone) + '</a>' : '') + '</div>';
        var maxRound = Math.max.apply(null, o.items.map(function (i) { return i.round; })), last = null;
        h += '<ul class="k-items">';
        o.items.slice().sort(function (a, b) { return a.round - b.round; }).forEach(function (it) {
            if (maxRound > 1 && it.round !== last) { last = it.round; h += '<li class="k-round' + (it.round === maxRound ? ' is-latest' : '') + '">Ronda ' + it.round + (it.round === maxRound ? ' · nueva' : '') + '</li>'; }
            h += '<li><span class="k-qty">' + it.qty + '</span><div><div class="k-name">' + esc(it.name) + '</div>';
            if (it.mods.length) h += '<div class="k-extras">' + it.mods.map(function (m) { return '<span class="k-extra">+ ' + esc(m) + '</span>'; }).join('') + '</div>';
            if (it.note) h += '<div class="k-note">' + ic('sticky-note') + ' ' + esc(it.note) + '</div>';
            h += '</div></li>';
        });
        h += '</ul>';
        if (o.onote) h += '<div class="k-onote">' + ic('comment') + ' ' + esc(o.onote) + '</div>';
        if (key === 'new') {
            var sg = eta(o);
            h += '<div class="k-eta" data-id="' + o.id + '" data-base="' + sg + '"><div class="k-eta-label">' + ic('clock') + ' ' + (o.type === 'delivery' ? 'Llegada al cliente en' : (o.type === 'pickup' ? 'Listo para recoger en' : 'Listo para servir en'));
            if (o.scheduled) h += '<small>Programado: empezar a las <span class="k-start-at" data-sched="' + o.scheduled + '">…</span></small>';
            h += '</div><div class="k-eta-ctl"><button type="button" class="k-step" data-step="-5" title="Restar 5 minutos" aria-label="Restar 5 minutos">' + ic('minus') + '</button><strong class="k-eta-val">' + sg + ' min</strong><button type="button" class="k-step" data-step="5" title="Sumar 5 minutos" aria-label="Sumar 5 minutos">' + ic('plus') + '</button><button type="button" class="k-step k-reset" data-reset="1" title="Volver al tiempo sugerido (' + sg + ' min)" aria-label="Volver al tiempo sugerido">' + ic('rotate') + '</button></div><div class="k-eta-hint">Sugerido ' + sg + ' min según el plato y la carga de cocina</div></div>';
        } else if (key === 'preparing') {
            var due = o.promisedAt, late = due && due < vnow();
            h += '<div class="k-eta k-eta-set' + (late ? ' is-late' : '') + '"' + (due ? ' data-due="' + due + '"' : '') + '><div class="k-eta-label">' + ic('clock') + (due ? ' ' + (o.type === 'delivery' ? 'Llegada prometida' : 'Listo a las') + ' <strong>' + hm(due) + '</strong> <small class="k-left">…</small>' : ' Sin hora prometida al cliente') + '</div><div class="k-eta-ctl"><span class="k-eta-cap">Avisar retraso</span>';
            [5, 10, 15].forEach(function (m) { h += '<button type="button" class="k-step k-plus" title="Retrasar ' + m + ' minutos" data-extend="' + m + '" data-id="' + o.id + '">' + ic('plus') + ' ' + m + ' min</button>'; });
            h += '</div></div>';
        }
        h += '<div class="k-actions">';
        if (PREV[key]) h += '<button type="button" class="k-undo" title="Regresar este pedido a «' + STATUS[PREV[key]].label + '»" aria-label="Regresar" data-move="' + PREV[key] + '" data-id="' + o.id + '">' + ic('arrow-left') + '</button>';
        h += '<button type="button" class="k-go' + (key === 'new' ? ' k-start' : '') + '" ' + (key === 'new' ? 'data-start="' + o.id + '"' : 'data-move="' + col.next + '" data-id="' + o.id + '"') + '>' + esc(col.action) + ' ' + ic('chevron-right') + '</button></div></article>';
        return h;
    }

    function board() {
        var el = document.getElementById('kitchen-board');
        var by = { new: [], preparing: [], ready: [] }, done = [], counts = { all: 0, dine_in: 0, pickup: 0, delivery: 0 };
        orders.forEach(function (o) {
            if (by[o.status]) { by[o.status].push(o); counts.all++; counts[o.type]++; } else if (o.status === 'delivered' && o.updatedAt >= vnow() - 3 * 3600000) done.push(o);
        });
        done.sort(function (a, b) { return b.updatedAt - a.updatedAt; });
        var h = '<div class="kx-bar" role="toolbar" aria-label="Filtrar por tipo de pedido"><button type="button" class="kx-chip" data-f="all">Todos <b>' + counts.all + '</b></button>';
        Object.keys(TYPES).forEach(function (k) { h += '<button type="button" class="kx-chip" data-f="' + k + '">' + ic(TYPE_ICON[k]) + ' ' + esc(TYPES[k]) + ' <b>' + counts[k] + '</b></button>'; });
        h += '<span class="kx-spacer"></span><button type="button" class="kx-btn kx-busy' + (busy ? ' is-on' : '') + '" data-busy="1" title="Suma minutos al tiempo estimado de los pedidos nuevos" aria-pressed="' + busy + '">' + ic('flame') + ' Modo ocupado' + (busy ? ' activo (+' + CFG.busyExtra + ' min)' : '') + '</button></div><div class="kx-cols">';
        Object.keys(STATUS).forEach(function (key) {
            var col = STATUS[key];
            h += '<section class="kx-col" aria-label="' + col.label + '"><div class="kx-colhead" style="--c:' + col.color + '">' + col.label + ' <span class="n">' + by[key].length + '</span></div>';
            by[key].forEach(function (o) { h += cardHtml(o, key, col); });
            if (!by[key].length) h += '<div class="k-empty">' + ic(EMPTY[key][0]) + EMPTY[key][1] + '</div>';
            h += '</section>';
        });
        h += '</div>';
        if (done.length) {
            h += '<div class="kx-done"><h5>Entregados (últimas 3 h)</h5>';
            done.slice(0, 12).forEach(function (o) { h += '<span>' + ic('check') + ' ' + esc(o.number) + (o.table ? ' · Mesa ' + esc(o.table) : '') + ' <a href="#" data-move="ready" data-id="' + o.id + '" title="Reabrir" aria-label="Reabrir">' + ic('rotate') + '</a></span>'; });
            h += '</div>';
        }
        el.innerHTML = h;
        el.setAttribute('data-filter', filter);
        var ids = by.new.map(function (o) { return o.id; });
        if (known !== null) {
            ids.filter(function (id) { return known.indexOf(id) === -1; }).forEach(function (id) { var c = el.querySelector('.k-card[data-id="' + id + '"]'); if (c) c.classList.add('is-new'); });
        }
        known = ids;
        paintEta(); tick();
    }

    function paintEta() {
        document.querySelectorAll('.k-eta[data-base]').forEach(function (box) {
            var id = box.getAttribute('data-id'), base = parseInt(box.getAttribute('data-base'), 10);
            adj[id] = Math.min(MAX - base, Math.max(MIN - base, adj[id] || 0));
            var m = Math.min(MAX, Math.max(MIN, base + adj[id]));
            box.querySelector('.k-eta-val').textContent = m + ' min';
            box.classList.toggle('is-adjusted', adj[id] !== 0);
            box.querySelector('[data-step="-5"]').disabled = m <= MIN;
            box.querySelector('[data-step="5"]').disabled = m >= MAX;
            var at = box.querySelector('.k-start-at');
            if (at) at.textContent = hm(parseInt(at.getAttribute('data-sched'), 10) - m * 60000);
        });
    }

    function tick() {
        var now = vnow();
        document.querySelectorAll('.k-eta-set[data-due]').forEach(function (box) {
            var diff = Math.round((parseInt(box.getAttribute('data-due'), 10) - now) / 60000), el = box.querySelector('.k-left');
            if (el) el.textContent = diff >= 0 ? '· faltan ' + diff + ' min' : '· retrasado ' + Math.abs(diff) + ' min';
            box.classList.toggle('is-late', diff < 0);
        });
        document.querySelectorAll('.k-timer').forEach(function (t) {
            var m = Math.max(0, Math.floor((now - parseInt(t.getAttribute('data-ts'), 10)) / 60000));
            t.textContent = m < 60 ? m + ' min' : Math.floor(m / 60) + ' h ' + (m % 60) + ' min';
            var limit = parseInt(t.getAttribute('data-limit') || '20', 10);
            t.classList.toggle('warn', m >= limit * 0.6 && m < limit);
            t.classList.toggle('late', m >= limit);
        });
        var live = document.getElementById('kx-updated'); if (live) live.textContent = 'En vivo · ' + hm(now);
    }

    // Como en la pantalla real: el tablero se refresca solo cada pocos segundos (no hay segundero).
    function refresh() {
        if (document.hidden || Date.now() - lastStep < 6000) return;
        board();
    }

    function setTheme(t) { document.querySelector('.kx').setAttribute('data-theme', t); }

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

    function find(id) { return orders.filter(function (o) { return o.id === parseInt(id, 10); })[0]; }
    function move(o, to, etaMin) {
        var from = o.status; if (!o || from === to) return;
        if (to === 'preparing' && from === 'new') { o.acceptedAt = vnow(); o.promisedAt = o.scheduled || vnow() + etaMin * 60000; }
        if (to === 'new') { o.acceptedAt = null; o.promisedAt = null; }
        o.status = to; o.updatedAt = vnow();
        if (to === 'ready') o.readyAt = vnow();
    }

    // La demo parte con 4 pedidos y recibe solo uno más por visita (sube MAX_INCOMING para que lleguen más).
    var MAX_INCOMING = 1, arrived = 0;
    function incoming() {
        if (arrived >= MAX_INCOMING) return;
        var activeNew = orders.filter(function (o) { return o.status === 'new'; }).length;
        if (activeNew < 5) {
            arrived++;
            var t = TEMPLATES[Math.floor(Math.random() * TEMPLATES.length)](); seq += 1;
            var o = Object.assign({ id: seq, number: 'PED-' + String(seq).padStart(6, '0'), status: 'new', createdAt: vnow(), scheduled: null }, t);
            if (t.customerFn) o.customer = t.customerFn(t.table);
            orders.push(o); board();
            if (document.getElementById('kitchen-sound').checked) beep();
        }
        if (arrived < MAX_INCOMING) setTimeout(incoming, 14000 + Math.random() * 10000);
    }

    document.addEventListener('click', function (e) {
        var b = document.getElementById('kitchen-board');
        var chip = e.target.closest('.kx-chip'); if (chip) { filter = chip.getAttribute('data-f'); b.setAttribute('data-filter', filter); return; }
        var step = e.target.closest('.k-eta[data-base] .k-step');
        if (step && !step.disabled) {
            var box = step.closest('.k-eta'), id = box.getAttribute('data-id');
            adj[id] = step.hasAttribute('data-reset') ? 0 : (adj[id] || 0) + parseInt(step.getAttribute('data-step'), 10);
            lastStep = Date.now(); paintEta(); return;
        }
        var start = e.target.closest('[data-start]');
        if (start) { var o = find(start.getAttribute('data-start')); var bx = b.querySelector('.k-eta[data-id="' + o.id + '"]'); move(o, 'preparing', bx ? Math.min(MAX, Math.max(MIN, parseInt(bx.getAttribute('data-base'), 10) + (adj[o.id] || 0))) : eta(o)); delete adj[o.id]; board(); return; }
        var mv = e.target.closest('[data-move]');
        if (mv) { e.preventDefault(); move(find(mv.getAttribute('data-id')), mv.getAttribute('data-move'), 0); board(); return; }
        var ex = e.target.closest('[data-extend]');
        if (ex) { var p = find(ex.getAttribute('data-id')); p.promisedAt = (p.promisedAt || vnow()) + parseInt(ex.getAttribute('data-extend'), 10) * 60000; board(); return; }
        if (e.target.closest('[data-busy]')) { busy = !busy; board(); return; }
        if (e.target.closest('#kx-test')) beep();
        if (e.target.closest('#kx-theme')) {
            setTheme(document.querySelector('.kx').getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        if (window.matchMedia('(prefers-color-scheme: dark)').matches) setTheme('dark');
        board();
        setInterval(refresh, 8000);
        setTimeout(incoming, 12000);
    });
})();
