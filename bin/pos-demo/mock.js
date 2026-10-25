/* API simulada del POS para la demo de la web (restaurante ficticio «Sabor Andino»).
   Intercepta fetch('/api/v1/pos/*'): nada sale a ningún servidor. */
(function () {
    var T0 = Date.now(), IMG = '/themes/master/assets/demo/pos/', V = '?v=1';
    var P = function (id, name, price, col, img, extra) { return Object.assign({ id: id, name: name, sku: null, price: price, has_variants: false, in_stock: true, stock: null, collection_id: col, image_url: IMG + img + '.svg' + V, variants: [] }, extra || {}); };
    var products = [
        P(1, 'Café americano', 12, 1, 'cafe'),
        P(2, 'Cappuccino', 15, 1, 'cappuccino', { has_variants: true, variants: [{ id: 21, label: 'Chico', price: 15, in_stock: true }, { id: 22, label: 'Grande', price: 20, in_stock: true }] }),
        P(3, 'Jugo natural', 14, 1, 'jugo'),
        P(4, 'Limonada', 12, 1, 'limonada'),
        P(5, 'Agua 500 ml', 5, 1, 'agua'),
        P(6, 'Gaseosa 2 L', 18, 1, 'gaseosa'),
        P(7, 'Empanada de queso', 8, 2, 'empanada'),
        P(8, 'Sándwich mixto', 22, 2, 'sandwich'),
        P(9, 'Plato del día', 35, 2, 'plato'),
        P(10, 'Ensalada fresca', 24, 2, 'ensalada'),
        P(11, 'Torta, porción', 18, 3, 'torta'),
        P(12, 'Flan casero', 12, 3, 'flan'),
        P(13, 'Helado', 14, 3, 'helado'),
        P(14, 'Brownie', 16, 3, 'brownie')
    ];
    var me = {
        user: { id: 1, name: 'Carla Rojas', initials: 'CR', is_supervisor: true, discount_limit_percent: 100 },
        tenant: { handle: 'sabor-andino', name: 'Sabor Andino' },
        currency: { symbol: 'Bs', decimals: 2 },
        settings: { profile: 'restaurant', ticket_width: '80', tables: true, kitchen: true, barcode: false, tips: true, require_shift: true, ticket_header: 'Sabor Andino', ticket_footer: '¡Gracias por visitarnos!' },
        whatsapp: true,
        terminals: [{ id: 1, name: 'Caja 1', shift: { id: 1, terminal_id: 1, status: 'open', opened_at: new Date().toISOString(), opening_cash: 200, expected_cash: 200, counted_cash: null, difference: null } }]
    };
    var catalog = {
        collections: [{ id: 1, name: 'Bebidas' }, { id: 2, name: 'Comida' }, { id: 3, name: 'Postres' }],
        products: products,
        methods: [{ id: 1, label: 'Efectivo', kind: 'cash', auto: false }, { id: 2, label: 'QR', kind: 'qr', auto: true }, { id: 3, label: 'Tarjeta', kind: 'card', auto: false }, { id: 4, label: 'Transferencia', kind: 'transfer', auto: false }],
        tables: [1, 2, 3, 4, 5, 6].map(function (n) { return { id: n, name: 'Mesa ' + n }; })
    };
    // Pedidos que los clientes hicieron desde su mesa (checkout del restaurante).
    var mins = function (m) { return new Date(Date.now() - m * 60000).toISOString(); };
    var orders = [
        { id: 901, short: '18', number: 'PED-000018', type: 'dine_in', type_label: 'En el local', table: '4', customer: 'Marco Quispe', phone: null, status: 'pending', paid: false, kitchen: 'preparing',
          payment: { kind: 'cash', label: 'Efectivo en caja', qr_status: null }, can_collect: true, by_pos: false, created_at: mins(6),
          items: [{ name: 'Plato del día', quantity: 2, unit_price: 35, total: 70, extras: ['Extra picante'], note: 'Uno sin cebolla' }, { name: 'Limonada', quantity: 2, unit_price: 12, total: 24, extras: [], note: null }], notes: null },
        { id: 902, short: '19', number: 'PED-000019', type: 'dine_in', type_label: 'En el local', table: '2', customer: 'Lucía Mamani', phone: '72345678', status: 'pending', paid: false, kitchen: 'new',
          payment: { kind: 'qr', label: 'Pago QR', qr_status: 'pending' }, can_collect: false, by_pos: false, created_at: mins(2), qrAt: Date.now(),
          items: [{ name: 'Sándwich mixto', quantity: 1, unit_price: 22, total: 22, extras: ['Queso extra'], note: null }, { name: 'Jugo natural', quantity: 1, unit_price: 14, total: 14, extras: [], note: null }], notes: 'Sin hielo, por favor.' },
        { id: 903, short: '17', number: 'PED-000017', type: 'pickup', type_label: 'Recoger', table: null, customer: 'Pedro Rojas', phone: '71234567', status: 'paid', paid: true, kitchen: 'ready',
          payment: { kind: 'qr', label: 'Pago QR', qr_status: null }, can_collect: false, by_pos: false, created_at: mins(14),
          items: [{ name: 'Empanada de queso', quantity: 4, unit_price: 8, total: 32, extras: [], note: null }], notes: null }
    ].map(function (o) { o.items_count = o.items.reduce(function (a, i) { return a + i.quantity; }, 0); o.total = o.items.reduce(function (a, i) { return a + i.total; }, 0); o.subtotal = o.total; return o; });
    var orderOf = function (id) { return orders.filter(function (o) { return o.id === id; })[0]; };
    var pendingCount = function () { return orders.filter(function (o) { return !o.paid; }).length; };
    var seq = 148, sales = {}, qrs = {};
    var json = function (status, body) { return Promise.resolve(new Response(JSON.stringify(body), { status: status, headers: { 'Content-Type': 'application/json' } })); };
    var wait = function (ms, fn) { return new Promise(function (r) { setTimeout(function () { r(fn()); }, ms); }); };

    // QR de ejemplo (no es un código válido): cuadrícula determinista.
    function qrImage(seed) {
        var n = 25, c = 8, rects = '', s = 0; for (var i = 0; i < seed.length; i++) s = (s * 31 + seed.charCodeAt(i)) >>> 0;
        var rnd = function () { s = (s * 1664525 + 1013904223) >>> 0; return s / 4294967296; };
        var finder = function (x, y) { return (x < 7 && y < 7) || (x >= n - 7 && y < 7) || (x < 7 && y >= n - 7); };
        for (var y = 0; y < n; y++) for (var x = 0; x < n; x++) {
            var on;
            if (finder(x, y)) {
                var ox = x >= n - 7 ? x - (n - 7) : x, oy = y >= n - 7 ? y - (n - 7) : y;
                on = ox === 0 || ox === 6 || oy === 0 || oy === 6 || (ox >= 2 && ox <= 4 && oy >= 2 && oy <= 4);
            } else { on = rnd() > 0.52; }
            if (on) rects += '<rect x="' + x * c + '" y="' + y * c + '" width="' + c + '" height="' + c + '"/>';
        }
        return 'data:image/svg+xml;utf8,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + n * c + ' ' + n * c + '"><rect width="100%" height="100%" fill="#fff"/><g fill="#14181F">' + rects + '</g></svg>');
    }

    function receiptFor(body, existing) {
        var items = existing ? existing.items.map(function (i) { return { name: i.name, quantity: i.quantity, unit_price: i.unit_price, total: i.total }; }) : body.items.map(function (i) {
            var p = products.filter(function (x) { return x.id === i.product_id; })[0], v = p.variants.filter(function (x) { return x.id === i.variant_id; })[0];
            var unit = v ? v.price : p.price;
            return { name: p.name + (v ? ' · ' + v.label : ''), quantity: i.quantity, unit_price: unit, total: unit * i.quantity };
        });
        var sub = items.reduce(function (a, i) { return a + i.total; }, 0), disc = Math.round(sub * (body.discount_percent || 0)) / 100, total = sub - disc, rest = total, change = 0;
        var pays = body.payments.map(function (p) {
            var m = catalog.methods.filter(function (x) { return x.id === p.method_id; })[0], applied = Math.min(rest, p.amount), ch = m.kind === 'cash' ? Math.max(0, (p.tendered || p.amount) - applied) : 0;
            rest -= applied; change += ch;
            return { method: m.label, kind: m.kind, amount: applied, tendered: m.kind === 'cash' ? (p.tendered || p.amount) : applied, change: ch };
        });
        var id = ++seq;
        var r = { id: id, number: 'DEMO-' + String(id).padStart(4, '0'), date: new Date().toISOString(), currency: 'Bs', items: items, subtotal: sub, discount: disc, total: total, payments: pays, change: change, nit: body.nit || null, tax_name: body.tax_name || null, invoice: 'manual' };
        sales[id] = r; return r;
    }

    var realFetch = window.fetch;
    window.fetch = function (url, opts) {
        url = String(url);
        if (url.indexOf('/api/v1/pos') !== 0) return realFetch.apply(this, arguments);
        var path = url.replace('/api/v1/pos', ''), method = (opts && opts.method) || 'GET', body = opts && opts.body ? JSON.parse(opts.body) : {};
        if (/^\/tenants\/[^/]+$/.test(path)) return json(200, { data: { handle: 'sabor-andino', name: 'Sabor Andino' } });
        if (path === '/me') return json(200, { data: me });
        if (path === '/catalog') return json(200, { data: catalog });
        // Simula un pedido nuevo que entra desde una mesa unos segundos después de abrir la demo.
        if (path === '/orders' && !orderOf(904) && Date.now() - T0 > 16000) {
            var n = { id: 904, short: '20', number: 'PED-000020', type: 'dine_in', type_label: 'En el local', table: '5', customer: 'Rosa Choque', phone: null, status: 'pending', paid: false, kitchen: 'new',
                payment: { kind: 'cash', label: 'Efectivo en caja', qr_status: null }, can_collect: true, by_pos: false, created_at: mins(0),
                items: [{ name: 'Ensalada fresca', quantity: 1, unit_price: 24, total: 24, extras: ['Aderezo aparte'], note: null }, { name: 'Café americano', quantity: 2, unit_price: 12, total: 24, extras: [], note: null }], notes: null };
            n.items_count = 3; n.total = 48; n.subtotal = 48; orders.unshift(n);
        }
        if (path === '/orders') return json(200, { data: { orders: orders.map(function (o) { return Object.assign({}, o, { items: undefined }); }), pending: pendingCount() } });
        var om = path.match(/^\/orders\/(\d+)(\/(verify|collect))?$/);
        if (om) {
            var ord = orderOf(parseInt(om[1], 10));
            if (!ord) return json(404, { error: 'not_found', message: 'Pedido no encontrado.' });
            if (om[3] === 'verify') { if (!ord.paid && ord.payment.qr_status === 'pending' && Date.now() - ord.qrAt > 4000) { ord.paid = true; ord.status = 'paid'; ord.payment.qr_status = null; } return wait(500, function () { return new Response(JSON.stringify({ data: { order: ord } }), { status: 200, headers: { 'Content-Type': 'application/json' } }); }); }
            if (om[3] === 'collect') {
                if (ord.paid) return json(422, { error: 'rejected', message: 'Este pedido ya está pagado.' });
                var rc = receiptFor({ items: [], discount_percent: 0, payments: body.payments, nit: body.nit, tax_name: body.tax_name }, ord);
                rc.number = ord.number; ord.paid = true; ord.status = 'paid'; ord.can_collect = false;
                return wait(400, function () { return new Response(JSON.stringify({ data: { receipt: rc, order: ord } }), { status: 201, headers: { 'Content-Type': 'application/json' } }); });
            }
            return json(200, { data: { order: ord } });
        }
        if (path === '/shift/open') return json(201, { data: { shift: me.terminals[0].shift } });
        if (path === '/qr' && method === 'POST') {
            var ref = 'demo-' + Math.random().toString(36).slice(2, 10);
            qrs[ref] = { reference: ref, status: 'pending', amount: body.amount, image: qrImage(ref), at: Date.now() };
            return wait(500, function () { return new Response(JSON.stringify({ data: { qr: qrs[ref] } }), { status: 201, headers: { 'Content-Type': 'application/json' } }); });
        }
        if (/^\/qr\//.test(path)) {
            var q = qrs[path.slice(4)];
            if (!q) return json(404, { error: 'not_found', message: 'QR no encontrado.' });
            if (q.status === 'pending' && Date.now() - q.at > 5000) q.status = 'paid'; // el «banco» confirma a los 5 s
            return json(200, { data: { qr: q } });
        }
        if (path === '/sales' && method === 'POST') return wait(400, function () { return new Response(JSON.stringify({ data: { receipt: receiptFor(body) } }), { status: 201, headers: { 'Content-Type': 'application/json' } }); });
        if (/^\/sales\/\d+\/receipt\/whatsapp$/.test(path)) return wait(700, function () { return new Response(JSON.stringify({ data: { sent: true } }), { status: 202, headers: { 'Content-Type': 'application/json' } }); });
        if (path === '/shift/close') return json(200, { data: { shift: Object.assign({}, me.terminals[0].shift, { status: 'closed', expected_cash: 200, counted_cash: body.counted_cash, difference: body.counted_cash - 200 }), summary: { sales_count: 0, sales_total: 0 } } });
        if (path === '/logout') return json(200, { data: { ok: true } });
        return json(404, { error: 'not_found', message: 'Función no disponible en la demo.' });
    };
})();
