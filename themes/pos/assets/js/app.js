/* Market POS — Alpine. Habla solo con /api/v1/pos/*. Las ventas sin conexión se guardan y se envían al volver la señal. */
function posApp() {
    var API = '/api/v1/pos';
    var store = {
        get: function (k, d) { try { var v = localStorage.getItem(k); return v === null ? d : JSON.parse(v); } catch (e) { return d; } },
        set: function (k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} },
        del: function (k) { try { localStorage.removeItem(k); } catch (e) {} }
    };
    var uuid = function () {
        if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) { var r = Math.random() * 16 | 0; return (c === 'x' ? r : (r & 3 | 8)).toString(16); });
    };
    // Búsqueda sin tildes ni mayúsculas: «sand» encuentra «Sándwich».
    var fold = function (t) { return String(t || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase(); };
    var round2 = function (n) { return Math.round(n * 100) / 100; };

    return {
        // --- estado general
        handle: '', handleInput: '', tenant: null, token: null, me: null, catalog: { collections: [], products: [], methods: [], tables: [] },
        screen: 'gate', busy: false, error: '', toast: '', online: navigator.onLine, queue: [],
        // --- acceso
        login: '', pin: '',
        // --- turno
        terminalId: null, shift: null, openingCash: '', shiftSheet: false, counted: '', closeResult: null, closeError: '',
        // --- venta
        cat: 0, q: '', cart: [], discount: false, nit: '', razon: '', tableId: '', variantFor: null,
        // --- cobro
        payOpen: false, methodId: null, recv: '', payments: [], done: null, doneLocal: false, saleBusy: false, payError: '', qr: null, qrBusy: false, qrTimer: null, waPhone: '591', waBusy: false, waMsg: '', waOk: false,
        // --- extras
        mobile: false, searchOpen: false, calcOpen: false, c: { acc: null, op: null, cur: '0', fresh: true, hist: '' }, settingsOpen: false,
        prefs: store.get('aero.pos.prefs', { thumbs: false, paper: '', theme: 'auto' }),

        // ---------------------------------------------------------------- arranque
        init: function () {
            var self = this;
            this.handle = (location.pathname.split('/')[1] || '').toLowerCase().replace(/[^a-z0-9-]/g, '');
            this.queue = store.get('aero.pos.queue', []);
            window.addEventListener('online', function () { self.online = true; self.flush(); });
            window.addEventListener('offline', function () { self.online = false; });
            setInterval(function () { if (self.queue.length && navigator.onLine) self.flush(); }, 30000);
            if ('serviceWorker' in navigator) navigator.serviceWorker.register('/sw.js').catch(function () {});
            this.setTheme(this.prefs.theme);
            // En celular no se muestran fotos: el catálogo debe ser corto para llegar rápido al detalle de la venta.
            var mq = window.matchMedia('(max-width: 640px)');
            this.mobile = mq.matches;
            mq.addEventListener('change', function (e) { self.mobile = e.matches; });
            if (!this.handle) { this.screen = 'gate'; return; }
            this.resolveTenant();
        },
        resolveTenant: async function () {
            this.error = '';
            var cached = store.get('aero.pos.tenant.' + this.handle, null);
            try {
                var r = await this.api('GET', '/tenants/' + this.handle, null, true);
                this.tenant = r.data; store.set('aero.pos.tenant.' + this.handle, r.data);
            } catch (e) {
                if (e.status === 404) { this.handle = ''; this.error = 'No existe ese negocio.'; this.screen = 'gate'; return; }
                this.tenant = cached || { handle: this.handle, name: this.handle };
            }
            this.token = store.get('aero.pos.token.' + this.handle, null);
            if (this.token) { await this.boot(); } else { this.screen = 'login'; }
        },
        goHandle: function () {
            var h = (this.handleInput || '').trim().toLowerCase().replace(/[^a-z0-9-]/g, '');
            if (h) location.href = '/' + h;
        },
        boot: async function () {
            try {
                var me = await this.api('GET', '/me');
                this.me = me.data; store.set('aero.pos.me.' + this.handle, me.data);
            } catch (e) {
                if (e.status === 401) { this.logoutLocal(); return; }
                this.me = store.get('aero.pos.me.' + this.handle, null);
                if (!this.me) { this.screen = 'login'; return; }
            }
            try {
                var cat = await this.api('GET', '/catalog');
                this.catalog = cat.data; store.set('aero.pos.catalog.' + this.handle, cat.data);
            } catch (e) {
                this.catalog = store.get('aero.pos.catalog.' + this.handle, this.catalog);
            }
            var tid = store.get('aero.pos.terminal.' + this.handle, null);
            var t = (this.me.terminals || []).find(function (x) { return x.id === tid; }) || (this.me.terminals || [])[0];
            this.terminalId = t ? t.id : null;
            this.shift = t ? t.shift : null;
            this.screen = (this.shift || !this.me.settings.require_shift) ? 'sale' : 'shift';
            this.flush();
        },

        // ---------------------------------------------------------------- red
        api: async function (method, path, body, noAuth) {
            var headers = { 'Accept': 'application/json' };
            if (body) headers['Content-Type'] = 'application/json';
            if (!noAuth && this.token) headers['Authorization'] = 'Bearer ' + this.token;
            var res = await fetch(API + path, { method: method, headers: headers, body: body ? JSON.stringify(body) : undefined });
            var json = null;
            try { json = await res.json(); } catch (e) {}
            if (!res.ok) {
                var err = new Error((json && json.message) || 'Error ' + res.status);
                err.status = res.status; err.code = json && json.error; throw err;
            }
            return json;
        },
        say: function (m) { var s = this; s.toast = m; setTimeout(function () { if (s.toast === m) s.toast = ''; }, 2600); },

        // ---------------------------------------------------------------- acceso
        pinKey: function (k) {
            if (k === 'del') this.pin = this.pin.slice(0, -1);
            else if (this.pin.length < 6) this.pin += k;
        },
        doLogin: async function () {
            this.error = '';
            if (!this.login.trim() || this.pin.length < 4) { this.error = 'Escribe tu usuario y tu PIN.'; return; }
            this.busy = true;
            try {
                var r = await this.api('POST', '/tenants/' + this.handle + '/login', { login: this.login.trim(), pin: this.pin }, true);
                this.token = r.data.token; store.set('aero.pos.token.' + this.handle, this.token);
                store.set('aero.pos.login.' + this.handle, this.login.trim());
                this.pin = '';
                await this.boot();
            } catch (e) { this.error = e.message; this.pin = ''; }
            this.busy = false;
        },
        logoutLocal: function () {
            store.del('aero.pos.token.' + this.handle);
            this.token = null; this.me = null; this.cart = []; this.settingsOpen = false; this.pin = '';
            this.login = store.get('aero.pos.login.' + this.handle, '');
            this.screen = 'login';
        },
        logout: async function () {
            try { await this.api('POST', '/logout'); } catch (e) {}
            this.logoutLocal();
        },

        // ---------------------------------------------------------------- dinero
        money: function (n) {
            var s = (this.me && this.me.currency && this.me.currency.symbol) || 'Bs';
            return s + ' ' + Number(n || 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        // ---------------------------------------------------------------- turno
        terminal: function () { var id = this.terminalId; return ((this.me && this.me.terminals) || []).find(function (t) { return t.id === id; }); },
        keyNum: function (field, k) {
            var v = this[field] || '';
            if (k === 'del') { this[field] = v.slice(0, -1); return; }
            if (k === '.' && v.indexOf('.') >= 0) return;
            if (v.indexOf('.') >= 0 && v.split('.')[1].length >= 2) return;
            this[field] = v + k;
        },
        openShift: async function () {
            this.error = ''; this.busy = true;
            try {
                var r = await this.api('POST', '/shift/open', { terminal_id: this.terminalId, opening_cash: parseFloat(this.openingCash || '0') });
                this.shift = r.data.shift; this.openingCash = ''; this.screen = 'sale';
                var t = this.terminal(); if (t) t.shift = this.shift;
            } catch (e) { this.error = e.message; }
            this.busy = false;
        },
        pickTerminal: function (id) {
            this.terminalId = id; store.set('aero.pos.terminal.' + this.handle, id);
            var t = this.terminal(); this.shift = t ? t.shift : null;
            this.screen = (this.shift || !this.me.settings.require_shift) ? 'sale' : 'shift';
            this.settingsOpen = false;
        },
        closeShift: async function () {
            this.closeError = '';
            if (this.counted === '') { this.closeError = 'Escribe el efectivo contado.'; return; }
            this.busy = true;
            try {
                var r = await this.api('POST', '/shift/close', { shift_id: this.shift.id, counted_cash: parseFloat(this.counted) });
                this.closeResult = r.data;
            } catch (e) { this.closeError = e.message; }
            this.busy = false;
        },
        finishClose: function () {
            var t = this.terminal(); if (t) t.shift = null;
            this.shift = null; this.shiftSheet = false; this.closeResult = null; this.counted = '';
            this.screen = this.me.settings.require_shift ? 'shift' : 'sale';
        },

        // ---------------------------------------------------------------- catálogo y carrito
        collections: function () {
            var used = {};
            this.catalog.products.forEach(function (p) { used[p.collection_id] = 1; });
            return this.catalog.collections.filter(function (c) { return used[c.id]; });
        },
        colorOf: function (cid) {
            var i = this.catalog.collections.findIndex(function (c) { return c.id === cid; });
            return (i < 0 ? 0 : i % 4) + 1;
        },
        dotOf: function (cid) { return ['#F2A93B', '#17B890', '#E8734A', '#7C6CF0', '#E0558F'][(this.catalog.collections.findIndex(function (c) { return c.id === cid; }) + 5) % 5]; },
        products: function () {
            var q = fold(this.q.trim()), cat = this.cat;
            return this.catalog.products.filter(function (p) {
                return (!cat || p.collection_id === cat) && (!q || fold(p.name).indexOf(q) >= 0 || (p.sku && fold(p.sku).indexOf(q) >= 0));
            });
        },
        scan: function () {
            var q = this.q.trim().toLowerCase(); if (!q) return;
            var hit = this.catalog.products.find(function (p) { return p.sku && p.sku.toLowerCase() === q; });
            if (hit) { this.add(hit); this.q = ''; }
        },
        add: function (p, v) {
            if (p.has_variants && !v) { this.variantFor = p; return; }
            var key = p.id + ':' + (v ? v.id : 0);
            var line = this.cart.find(function (l) { return l.key === key; });
            if (line) line.qty++;
            else this.cart.push({ key: key, product_id: p.id, variant_id: v ? v.id : null, name: p.name + (v ? ' · ' + v.label : ''), price: v ? v.price : p.price, qty: 1 });
            this.variantFor = null;
        },
        openSearch: function () {
            var self = this; this.searchOpen = true;
            setTimeout(function () { if (self.$refs.searchBig) self.$refs.searchBig.focus(); }, 80);
        },
        closeSearch: function () { this.searchOpen = false; this.q = ''; },
        searchResults: function () {
            var q = fold(this.q.trim()); if (!q) return [];
            return this.catalog.products.filter(function (p) { return fold(p.name).indexOf(q) >= 0 || (p.sku && fold(p.sku).indexOf(q) >= 0); }).slice(0, 12);
        },
        showThumbs: function () { return !!this.prefs.thumbs && !this.mobile; },
        qtyOf: function (p) { return this.cart.filter(function (l) { return l.product_id === p.id; }).reduce(function (a, l) { return a + l.qty; }, 0); },
        inc: function (l) { l.qty++; },
        dec: function (l) { l.qty--; if (l.qty <= 0) this.cart = this.cart.filter(function (x) { return x !== l; }); },
        clearCart: function () { this.cart = []; this.discount = false; this.nit = ''; this.razon = ''; },
        subtotal: function () { return round2(this.cart.reduce(function (a, l) { return a + l.price * l.qty; }, 0)); },
        discountAmt: function () { return this.discount ? round2(this.subtotal() * 0.1) : 0; },
        total: function () { return round2(this.subtotal() - this.discountAmt()); },

        // ---------------------------------------------------------------- cobro
        openPay: function () {
            if (!this.cart.length) return;
            this.payments = []; this.recv = ''; this.payError = ''; this.done = null;
            var cash = this.catalog.methods.find(function (m) { return m.kind === 'cash'; }) || this.catalog.methods[0];
            this.methodId = cash ? cash.id : null; this.qr = null; this.stopQr(); this.payOpen = true;
        },
        isAutoQr: function () { var m = this.method(); return !!m && m.kind === 'qr' && !!m.auto; },
        pickMethod: function (m) {
            this.methodId = m.id; this.recv = ''; this.payError = '';
            if (!(m.kind === 'qr' && m.auto)) this.stopQr();
            else if (!this.qr || this.qr.amount !== this.remaining()) this.startQr();
            else this.pollQr();
        },
        startQr: async function () {
            this.stopQr(); this.qr = null; this.payError = '';
            if (!navigator.onLine) { this.payError = 'El cobro con QR necesita conexión.'; return; }
            this.qrBusy = true;
            try {
                var r = await this.api('POST', '/qr', { amount: this.remaining(), description: (this.tenant ? this.tenant.name : 'Venta') + ' · mostrador' });
                this.qr = r.data.qr; this.pollQr();
            } catch (e) { this.payError = e.message; }
            this.qrBusy = false;
        },
        pollQr: function () {
            var self = this; clearInterval(this.qrTimer);
            var tick = async function () {
                if (!self.qr || !self.payOpen || self.done) { clearInterval(self.qrTimer); return; }
                try {
                    var r = await self.api('GET', '/qr/' + self.qr.reference);
                    self.qr.status = r.data.qr.status;
                    if (self.qr.status === 'paid') { clearInterval(self.qrTimer); self.confirmPay(); }
                    else if (self.qr.status === 'expired' || self.qr.status === 'cancelled') { clearInterval(self.qrTimer); self.payError = 'El QR venció. Genera uno nuevo.'; }
                } catch (e) { /* sin red: se vuelve a intentar */ }
            };
            this.qrTimer = setInterval(tick, 3000); tick();
        },
        stopQr: function () { clearInterval(this.qrTimer); this.qrTimer = null; },
        method: function () { var id = this.methodId; return this.catalog.methods.find(function (m) { return m.id === id; }); },
        isCash: function () { var m = this.method(); return !!m && m.kind === 'cash'; },
        paid: function () { return round2(this.payments.reduce(function (a, p) { return a + p.amount; }, 0)); },
        remaining: function () { return Math.max(0, round2(this.total() - this.paid())); },
        recvNum: function () { return this.recv === '' ? 0 : parseFloat(this.recv); },
        changeNow: function () { return this.isCash() ? Math.max(0, round2(this.recvNum() - this.remaining())) : 0; },
        shortNow: function () { return this.isCash() ? Math.max(0, round2(this.remaining() - this.recvNum())) : 0; },
        canConfirm: function () { return !this.saleBusy && this.method() && (this.isAutoQr() ? !!this.qr && this.qr.status === 'paid' : this.isCash() ? this.recvNum() >= this.remaining() - 0.004 : true); },
        canSplit: function () { return this.recvNum() > 0 && this.recvNum() < this.remaining() - 0.004; },
        quickBills: function () {
            var r = this.remaining(), out = [{ label: 'Exacto', v: r }];
            [10, 20, 50, 100, 200].forEach(function (b) { out.push({ label: String(b), v: b }); });
            return out;
        },
        addSplit: function () {
            var m = this.method(); if (!m || !this.canSplit()) return;
            this.payments.push({ method_id: m.id, label: m.label, kind: m.kind, amount: this.recvNum(), tendered: this.recvNum() });
            this.recv = '';
        },
        payload: function () {
            var self = this, list = this.payments.map(function (p) { return { method_id: p.method_id, amount: p.amount, tendered: p.tendered }; });
            var m = this.method();
            list.push(this.isCash()
                ? { method_id: m.id, amount: this.remaining(), tendered: this.recvNum() }
                : (this.isAutoQr() ? { method_id: m.id, amount: this.remaining(), qr_reference: this.qr.reference } : { method_id: m.id, amount: this.remaining() }));
            return {
                client_uuid: uuid(), shift_id: this.shift ? this.shift.id : null,
                items: this.cart.map(function (l) { return { product_id: l.product_id, variant_id: l.variant_id, quantity: l.qty }; }),
                discount_percent: this.discount ? 10 : 0, nit: this.nit.trim() || null, tax_name: this.razon.trim() || null,
                table_id: this.tableId || null, payments: list
            };
        },
        confirmPay: async function () {
            if (!this.canConfirm()) return;
            this.saleBusy = true; this.payError = '';
            var body = this.payload(), localTotal = this.total(), localCart = this.cart.slice(), change = this.changeNow();
            var payList = body.payments.slice(), methods = this.catalog.methods;
            try {
                var r = await this.api('POST', '/sales', body);
                this.done = r.data.receipt; this.doneLocal = false;
            } catch (e) {
                if (e.status) { this.payError = e.message; this.saleBusy = false; return; } // el servidor respondió: se corrige
                // Sin conexión: se guarda y se envía luego.
                this.queue.push(body); store.set('aero.pos.queue', this.queue);
                this.done = {
                    number: 'Pendiente', currency: (this.me.currency || {}).symbol || 'Bs', date: new Date().toISOString(),
                    items: localCart.map(function (l) { return { name: l.name, quantity: l.qty, unit_price: l.price, total: round2(l.price * l.qty) }; }),
                    subtotal: this.subtotal(), discount: this.discountAmt(), total: localTotal, change: change,
                    payments: payList.map(function (p) { var m = methods.find(function (x) { return x.id === p.method_id; }) || {}; return { method: m.label, kind: m.kind, amount: p.amount, tendered: p.tendered || p.amount, change: 0 }; }),
                    nit: body.nit, tax_name: body.tax_name, invoice: 'manual'
                };
                this.doneLocal = true;
            }
            this.saleBusy = false;
        },
        newSale: function () { this.waPhone = '591'; this.waMsg = ''; this.waOk = false; this.stopQr(); this.qr = null; this.payOpen = false; this.done = null; this.clearCart(); this.payments = []; this.recv = ''; this.refreshShift(); },
        refreshShift: async function () {
            try { var me = await this.api('GET', '/me'); this.me = me.data; var t = this.terminal(); if (t) this.shift = t.shift; } catch (e) {}
        },
        flush: async function () {
            if (!this.queue.length || !this.token) return;
            var left = [];
            for (var i = 0; i < this.queue.length; i++) {
                try { await this.api('POST', '/sales', this.queue[i]); }
                catch (e) { if (!e.status) { left = left.concat(this.queue.slice(i)); break; } /* rechazada por el servidor: se descarta */ }
            }
            if (left.length !== this.queue.length) this.say((this.queue.length - left.length) + ' venta(s) enviada(s).');
            this.queue = left; store.set('aero.pos.queue', left);
        },

        // ---------------------------------------------------------------- recibo
        paper: function () { return this.prefs.paper || (this.me && this.me.settings.ticket_width) || '80'; },
        sendWhatsapp: async function () {
            if (!this.done || !this.done.id || this.waBusy) return;
            this.waBusy = true; this.waMsg = ''; this.waOk = false;
            try {
                await this.api('POST', '/sales/' + this.done.id + '/receipt/whatsapp', { phone: this.waPhone });
                this.waOk = true; this.waMsg = 'Recibo enviado.';
            } catch (e) { this.waMsg = e.message; }
            this.waBusy = false;
        },
        printReceipt: function () {
            var r = this.done; if (!r) return;
            var w = this.paper() === '58' ? '48mm' : '72mm', self = this;
            var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
            var money = function (n) { return self.money(n); };
            var head = (this.me.settings.ticket_header || this.tenant.name);
            var html = '<html><head><meta charset="utf-8"><style>@page{margin:0}body{font:12px/1.35 monospace;width:' + w + ';margin:0 auto;padding:4mm 0}h1{font-size:14px;text-align:center;margin:0 0 4px}.r{display:flex;justify-content:space-between;gap:6px}hr{border:0;border-top:1px dashed #000;margin:6px 0}.c{text-align:center}</style></head><body>' +
                '<h1>' + esc(head) + '</h1><div class="c">Venta ' + esc(r.number) + '<br>' + new Date(r.date).toLocaleString('es-BO') + '</div><hr>' +
                r.items.map(function (i) { return '<div>' + esc(i.name) + '</div><div class="r"><span>' + i.quantity + ' x ' + money(i.unit_price) + '</span><span>' + money(i.total) + '</span></div>'; }).join('') +
                '<hr><div class="r"><span>Subtotal</span><span>' + money(r.subtotal) + '</span></div>' +
                (r.discount ? '<div class="r"><span>Descuento</span><span>-' + money(r.discount) + '</span></div>' : '') +
                '<div class="r"><b>TOTAL</b><b>' + money(r.total) + '</b></div><hr>' +
                r.payments.map(function (p) { return '<div class="r"><span>' + esc(p.method) + '</span><span>' + money(p.tendered || p.amount) + '</span></div>'; }).join('') +
                (r.change ? '<div class="r"><span>Cambio</span><span>' + money(r.change) + '</span></div>' : '') +
                (r.nit ? '<hr><div>NIT/CI: ' + esc(r.nit) + (r.tax_name ? '<br>' + esc(r.tax_name) : '') + '</div>' : '') +
                '<hr><div class="c">' + esc(this.me.settings.ticket_footer || '¡Gracias por su compra!') + '<br>Factura: se entrega de forma manual</div></body></html>';
            var f = document.createElement('iframe');
            f.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0';
            document.body.appendChild(f);
            f.contentDocument.open(); f.contentDocument.write(html); f.contentDocument.close();
            setTimeout(function () { f.contentWindow.focus(); f.contentWindow.print(); setTimeout(function () { f.remove(); }, 1500); }, 150);
        },

        // ---------------------------------------------------------------- calculadora
        calcKey: function (k) {
            var c = this.c;
            if (/^[0-9]$/.test(k) || k === '.') {
                var cur = c.fresh ? '' : c.cur;
                if (k === '.' && cur.indexOf('.') >= 0) return;
                if (k === '.' && cur === '') cur = '0';
                c.cur = (cur === '0' && k !== '.') ? k : cur + k; c.fresh = false; return;
            }
            if (k === 'C') { this.c = { acc: null, op: null, cur: '0', fresh: true, hist: '' }; return; }
            var n = parseFloat(c.cur), acc = c.acc;
            if (acc !== null && c.op && !c.fresh) {
                acc = c.op === '+' ? acc + n : c.op === '−' ? acc - n : c.op === '×' ? acc * n : (n === 0 ? 0 : acc / n);
            } else if (acc === null || c.fresh === false) { acc = n; }
            acc = round2(acc);
            if (k === '=') { this.c = { acc: null, op: null, cur: String(acc), fresh: true, hist: '' }; }
            else { this.c = { acc: acc, op: k, cur: String(acc), fresh: true, hist: acc + ' ' + k }; }
        },

        // ---------------------------------------------------------------- ajustes
        savePrefs: function () { store.set('aero.pos.prefs', this.prefs); },
        setTheme: function (t) {
            this.prefs.theme = t; this.savePrefs();
            try { localStorage.setItem('aero.pos.theme', t); } catch (e) {}
            if (t === 'auto') document.documentElement.removeAttribute('data-theme'); else document.documentElement.setAttribute('data-theme', t);
        },
        toggleDark: function () {
            var dark = document.documentElement.getAttribute('data-theme') === 'dark' ||
                (!document.documentElement.getAttribute('data-theme') && window.matchMedia('(prefers-color-scheme: dark)').matches);
            this.setTheme(dark ? 'light' : 'dark');
        },
        closeAll: function () { if (this.searchOpen) this.closeSearch(); this.calcOpen = false; this.settingsOpen = false; this.variantFor = null; if (!this.done) { this.payOpen = false; this.stopQr(); } }
    };
}
