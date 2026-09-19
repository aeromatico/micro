/* PWA de chat — Alpine. Habla solo con /api/v1/chat (plugin aero/chat). */
(function () {
    'use strict';

    var API = '/api/v1/chat';
    var KEY = 'aero.chat.';
    var PALETTE = ['#17695a', '#b4532a', '#3b5fa8', '#8a3f7a', '#7a6a12', '#2f6f8f'];
    var PLATFORMS = { whatsapp: 'WhatsApp', facebook: 'Messenger', instagram: 'Instagram', telegram: 'Telegram', sms: 'SMS' };

    function store(k, v) {
        try { if (v === undefined) return localStorage.getItem(KEY + k); if (v === null) localStorage.removeItem(KEY + k); else localStorage.setItem(KEY + k, v); } catch (e) { return null; }
    }

    window.chatApp = function () {
        return {
            screen: 'boot', handle: '', tenant: null, user: null, token: null,
            gateInput: '', gateError: '', loginForm: { login: '', password: '' }, loginError: '', busy: false,
            accounts: [], agents: [], convs: [], accountId: null, filter: 'all', q: '', loading: true,
            current: null, msgs: [], draft: '', mode: 'reply', sheet: '', delegateNote: '',
            crm: null, pay: null, shop: null, shopQ: '', shopResults: [], shopLoading: false, shopBusy: false, shopCart: [], shopForm: { gateway: null, notes: '', addr1: '', city: '', phone: '', notify: true }, payBusy: false, payForm: { amount: '', description: '', days: 1, bank: null, notify: true }, crmTab: 'contact', crmLoading: false, crmError: '', crmBusy: false, deptId: null,
            _allTabs: [{ id: 'contact', label: 'Contacto' }, { id: 'ticket', label: 'Ticket' }, { id: 'lead', label: 'Lead' }, { id: 'sale', label: 'Venta' }, { id: 'pay', label: 'Cobro' }],
            ticketStatuses: [{ id: 'open', label: 'Abierto' }, { id: 'pending', label: 'En espera' }, { id: 'resolved', label: 'Resuelto' }, { id: 'closed', label: 'Cerrado' }],
            leadStatuses: [{ id: 'new', label: 'Nuevo' }, { id: 'contacted', label: 'Contactado' }, { id: 'qualified', label: 'Calificado' }, { id: 'disqualified', label: 'Descartado' }],
            toast: '', theme: 'auto', installPrompt: null, online: navigator.onLine,
            filters: [{ id: 'all', label: 'Todos' }, { id: 'mine', label: 'Míos' }, { id: 'free', label: 'Sin asignar' }],
            _timers: [], _toastTimer: null, _seq: 0,

            // ---------- arranque ----------
            init: function () {
                var self = this;
                this.theme = store('theme') || 'auto';
                window.addEventListener('online', function () { self.online = true; self.refresh(); });
                window.addEventListener('offline', function () { self.online = false; });
                window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); self.installPrompt = e; });
                window.addEventListener('popstate', function () { self.current = null; self.sheet = ''; });
                document.addEventListener('visibilitychange', function () { if (!document.hidden) self.refresh(); });

                if ('serviceWorker' in navigator) navigator.serviceWorker.register('/sw.js').catch(function () {});

                var handle = (location.pathname.split('/')[1] || '').toLowerCase();
                if (!handle) {
                    this.gateInput = store('last') || '';
                    this.screen = 'gate';
                    return;
                }
                this.enter(handle);
            },

            async enter(handle) {
                this.handle = handle;
                try {
                    var t = await fetch(API + '/tenants/' + encodeURIComponent(handle), { headers: { Accept: 'application/json' } });
                    if (t.status === 404) { this.gateError = 'No existe el espacio "' + handle + '".'; this.gateInput = handle; this.screen = 'gate'; history.replaceState(null, '', '/'); return; }
                    if (t.ok) this.tenant = (await t.json()).data;
                } catch (e) {
                    // Sin red: si ya hubo sesión, se abre igual con lo último conocido.
                    this.tenant = { handle: handle, name: handle };
                }
                document.title = (this.tenant ? this.tenant.name : handle) + ' · Chat';
                store('last', handle);
                this.token = store('token.' + handle);
                if (!this.token) { this.screen = 'login'; return; }
                try {
                    var me = await this.api('/me');
                    this.user = me.user; this.tenant = me.tenant;
                    this.startInbox();
                } catch (e) {
                    if (e.status !== 401) { this.screen = 'login'; }
                }
            },

            submitGate: function () {
                var h = this.gateInput.trim().toLowerCase().replace(/^\/+|\/+$/g, '');
                if (!/^[a-z0-9][a-z0-9-]{0,62}$/.test(h)) { this.gateError = 'Usa solo letras, números y guiones.'; return; }
                location.href = '/' + h;   // navegación completa: el manifest lleva el espacio
            },

            async login() {
                this.busy = true; this.loginError = '';
                try {
                    var res = await fetch(API + '/tenants/' + encodeURIComponent(this.handle) + '/login', {
                        method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(this.loginForm),
                    });
                    var body = await res.json().catch(function () { return {}; });
                    if (!res.ok) throw new Error(res.status === 429 ? 'Demasiados intentos. Espera un minuto.' : (body.message || 'No se pudo iniciar sesión.'));
                    this.token = body.data.token; this.user = body.data.user; this.tenant = body.data.tenant;
                    store('token.' + this.handle, this.token);
                    this.loginForm = { login: '', password: '' };
                    this.startInbox();
                } catch (e) {
                    this.loginError = e.message;
                } finally { this.busy = false; }
            },

            logout: function (remote) {
                if (remote && this.token) fetch(API + '/logout', { method: 'POST', headers: { Authorization: 'Bearer ' + this.token, Accept: 'application/json' } }).catch(function () {});
                store('token.' + this.handle, null);
                this.stopTimers();
                this.token = null; this.user = null; this.convs = []; this.msgs = []; this.current = null; this.sheet = '';
                this.screen = 'login';
            },

            // ---------- API ----------
            async api(path, opts) {
                opts = opts || {};
                var res;
                try {
                    res = await fetch(API + path, {
                        method: opts.method || 'GET',
                        headers: { Authorization: 'Bearer ' + this.token, Accept: 'application/json', 'Content-Type': 'application/json' },
                        body: opts.body ? JSON.stringify(opts.body) : undefined,
                    });
                } catch (e) { this.online = false; throw { status: 0, message: 'Sin conexión.' }; }
                this.online = true;
                if (res.status === 401) { this.logout(false); throw { status: 401, message: 'Sesión vencida.' }; }
                var body = await res.json().catch(function () { return {}; });
                if (!res.ok) throw { status: res.status, message: body.message || 'Error inesperado.' };
                return body.data !== undefined && body.meta === undefined ? body.data : body;
            },

            // ---------- bandeja ----------
            startInbox: function () {
                var self = this;
                this.screen = 'inbox';
                this.refresh(true);
                this.stopTimers();
                this._timers.push(setInterval(function () { if (!document.hidden) self.refresh(); }, 8000));
                this._timers.push(setInterval(function () { if (!document.hidden && self.current) self.loadMsgs(false); }, 4000));
            },
            stopTimers: function () { this._timers.forEach(clearInterval); this._timers = []; },

            async refresh(first) {
                try {
                    var r = await Promise.all([this.api('/accounts'), this.api('/agents'), this.fetchConvs()]);
                    this.accounts = r[0]; this.agents = r[1]; this.applyConvs(r[2]);
                } catch (e) { /* el banner de conexión ya avisa */ }
                this.loading = false;
            },
            async loadConvs() { try { this.applyConvs(await this.fetchConvs()); } catch (e) {} this.loading = false; },
            fetchConvs: function () {
                var p = new URLSearchParams();
                if (this.accountId) p.set('account_id', this.accountId);
                if (this.filter !== 'all') p.set('filter', this.filter);
                if (this.q.trim()) p.set('q', this.q.trim());
                return this.api('/conversations?' + p.toString());
            },
            applyConvs: function (res) {
                this.convs = res.data || [];
                if (this.current) {
                    var fresh = this.convs.find(function (c) { return c.id === this.current.id; }, this);
                    if (fresh) this.current = Object.assign(this.current, fresh, { unread_count: 0 });
                }
            },
            pickAccount: function (id) { this.accountId = id; this.loading = true; this.loadConvs(); },
            setFilter: function (id) { this.filter = id; this.loading = true; this.loadConvs(); },

            // ---------- conversación ----------
            async openConv(c) {
                var wasOpen = this.current;
                this.current = c; this.msgs = []; this.draft = ''; this.mode = 'reply'; this.crm = null;
                this.pay = null; this.shop = null; this.shopCart = []; this.shopResults = [];
                if (this.sheet === 'crm') { this.loadCrm(); this.loadPay(); this.loadShop(); }
                if (!wasOpen) history.pushState({ chat: c.id }, '');
                await this.loadMsgs(true);
                if (c.unread_count > 0) {
                    c.unread_count = 0;
                    this.api('/conversations/' + c.id + '/read', { method: 'POST' }).then(this.refreshAccounts.bind(this)).catch(function () {});
                }
            },
            closeChat: function () { if (history.state && history.state.chat) history.back(); else this.current = null; },
            async refreshAccounts() { try { this.accounts = await this.api('/accounts'); } catch (e) {} },

            async loadMsgs(initial) {
                var c = this.current; if (!c) return;
                var path = '/conversations/' + c.id + '/messages';
                if (!initial) {
                    var last = this.msgs.filter(function (m) { return !String(m.id).startsWith('tmp'); }).pop();
                    if (last) path += '?after=' + encodeURIComponent(last.at);
                }
                try {
                    var rows = await this.api(path);
                    if (!this.current || this.current.id !== c.id) return;
                    if (initial) { this.msgs = rows; this.scrollDown(true); return; }
                    var known = {}; this.msgs.forEach(function (m) { known[m.id] = 1; });
                    var fresh = rows.filter(function (m) { return !known[m.id]; });
                    if (fresh.length) {
                        this.msgs = this.msgs.concat(fresh); this.scrollDown(false);
                        var paid = fresh.find(function (m) { return m.type === 'payment'; });
                        if (paid) { this.notify(paid.body); if (this.sheet === 'crm') { this.loadPay(); this.loadShop(); } }
                    }
                } catch (e) {}
            },

            async send() {
                var text = this.draft.trim(); if (!text || !this.current) return;
                var c = this.current, isNote = this.mode === 'note', tmp = 'tmp' + (++this._seq);
                this.draft = ''; this.$nextTick(function () { var ta = document.querySelector('.composer textarea'); if (ta) autoGrow(ta); });
                var item = isNote
                    ? { kind: 'note', id: tmp, body: text, by: this.user, at: new Date().toISOString() }
                    : { kind: 'message', id: tmp, direction: 'outbound', body: text, status: 'sending', at: new Date().toISOString() };
                this.msgs.push(item); this.scrollDown(true);
                try {
                    var res = await this.api('/conversations/' + c.id + (isNote ? '/note' : '/reply'), { method: 'POST', body: { body: text } });
                    var i = this.msgs.findIndex(function (m) { return m.id === tmp; });
                    if (i >= 0) this.msgs.splice(i, 1, isNote ? Object.assign(item, { id: res.id }) : res);
                    if (!isNote) {
                        c.last_message = { body: text, direction: 'outbound' }; c.last_message_at = new Date().toISOString();
                        if (!c.assigned_to) c.assigned_to = this.user;
                    }
                } catch (e) {
                    if (isNote) { this.msgs = this.msgs.filter(function (m) { return m.id !== tmp; }); this.draft = text; }
                    else { item.status = 'failed'; item.failed_reason = e.message; }
                    this.notify('No se envió: ' + e.message, 8000);
                }
            },

            async delegate(agentId) {
                var c = this.current; if (!c) return;
                try {
                    var res = await this.api('/conversations/' + c.id + '/delegate', { method: 'POST', body: { agent_id: agentId, note: this.delegateNote.trim() || null } });
                    c.assigned_to = res.assigned_to;
                    this.msgs.push({ kind: 'event', id: 'tmp' + (++this._seq), type: 'delegated', by: this.user, data: { to: res.assigned_to }, body: this.delegateNote.trim() || null, at: new Date().toISOString() });
                    this.notify(res.assigned_to ? 'Delegada a ' + res.assigned_to.name : 'Conversación sin asignar');
                    this.delegateNote = ''; this.sheet = ''; this.scrollDown(true); this.refreshAgents();
                } catch (e) { this.notify(e.message); }
            },
            async refreshAgents() { try { this.agents = await this.api('/agents'); } catch (e) {} },


            // ---------- CRM y cobros ----------
            openCrm: function () { this.sheet = 'crm'; this.loadCrm(); this.loadPay(); this.loadShop(); },
            async loadCrm() {
                var c = this.current; if (!c) return;
                this.crmLoading = true; this.crmError = '';
                try { this.setCrm(await this.api('/conversations/' + c.id + '/crm')); }
                catch (e) { this.crmError = e.message; }
                this.crmLoading = false;
            },
            setCrm: function (data) {
                this.crm = data;
                if (!this.crmOk && this.crmTab !== 'sale') this.crmTab = 'pay';
                if (data.departments && data.departments.length && !data.departments.some(function (d) { return d.id == this.deptId; }, this)) this.deptId = data.departments[0].id;
            },
            async crmCall(path, body) {
                var c = this.current; if (!c || this.crmBusy) return null;
                this.crmBusy = true; this.crmError = '';
                try {
                    var res = await this.api('/conversations/' + c.id + '/crm' + path, { method: 'POST', body: body || {} });
                    this.setCrm(res.panel || res); this.loadMsgs(false); return res;
                } catch (e) { this.notify(e.message); return null; }
                finally { this.crmBusy = false; }
            },
            toggleList: function (l) { this.crmCall('/lists', { list_id: l.id, on: !(this.crm.list_ids || []).includes(l.id) }); },
            async createTicket() { if (await this.crmCall('/ticket', { department_id: this.deptId })) this.notify('Ticket creado'); },
            setTicketStatus: function (st) { this.crmCall('/ticket/' + this.crm.ticket.id, { status: st }); },
            setLead: function (st) { this.crmCall('/lead', st ? { status: st } : {}); },
            async convertLead() { if (await this.crmCall('/lead/convert')) this.notify('Lead convertido en negocio'); },
            moveDeal: function (id) { this.crmCall('/deal', { stage_id: id }); },
            async sendQr(i) { if (await this.crmCall('/collections/' + i.id + '/qr')) { this.notify('Cobro enviado por QR'); this.sheet = ''; } },
            statusLabel: function (id) { var s = this.ticketStatuses.find(function (x) { return x.id === id; }); return s ? s.label : id; },
            money: function (n, cur) { return (cur === 'BOB' || !cur ? 'Bs ' : cur + ' ') + Number(n).toLocaleString('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },

            get crmOk() { return !!(this.crm && this.crm.crm && this.crm.whatsapp); },
            get crmTabs() {
                var self = this;
                return this._allTabs.filter(function (t) {
                    if (t.id === 'sale') return !!(self.shop && self.shop.available);
                    return self.crmOk || t.id === 'pay';
                });
            },
            get cartTotal() { return this.shopCart.reduce(function (t, l) { return t + l.price * l.qty; }, 0); },
            get cartNeedsShipping() { return this.shopCart.some(function (l) { return l.shipping; }); },


            async loadPay() {
                var c = this.current; if (!c) return;
                try { this.setPay(await this.api('/conversations/' + c.id + '/pay')); } catch (e) {}
            },
            setPay: function (d) {
                this.pay = d;
                if (d.banks && d.banks.length && !d.banks.some(function (b) { return b.id == this.payForm.bank; }, this)) this.payForm.bank = d.banks[0].id;
            },
            async createCharge() {
                var c = this.current; if (!c || this.payBusy) return;
                var amount = parseFloat(String(this.payForm.amount).replace(/\./g, '').replace(',', '.')) ;
                if (!(amount > 0)) { this.notify('Ingresa un monto válido.'); return; }
                this.payBusy = true;
                try {
                    this.setPay(await this.api('/conversations/' + c.id + '/pay/charge', { method: 'POST', body: {
                        amount: amount, description: this.payForm.description.trim(), days: this.payForm.days,
                        bank_account_id: this.payForm.bank, notify_on_paid: this.payForm.notify } }));
                    this.payForm.amount = ''; this.payForm.description = '';
                    this.notify('QR enviado al cliente'); this.loadMsgs(false);
                } catch (e) { this.notify(e.message); this.loadPay(); }
                finally { this.payBusy = false; }
            },
            // ---------- Ventas (tienda) ----------
            async loadShop() {
                var c = this.current; if (!c) return;
                try {
                    var d = await this.api('/conversations/' + c.id + '/shop');
                    this.shop = d;
                    if (d.available) {
                        if (d.gateways.length && !d.gateways.some(function (g) { return g.id == this.shopForm.gateway; }, this)) this.shopForm.gateway = d.gateways[0].id;
                        if (!this.shopResults.length) this.searchProducts();
                    }
                } catch (e) {}
            },
            async searchProducts() {
                this.shopLoading = true;
                try {
                    var r = await this.api('/shop/products?q=' + encodeURIComponent(this.shopQ.trim()));
                    this.shopResults = (r.data || []).map(function (p) { p._variant = 0; return p; });
                } catch (e) {}
                this.shopLoading = false;
            },
            addToCart: function (p) {
                var v = p.has_variants ? p.variants.find(function (x) { return x.id === p._variant; }) : null;
                if (p.has_variants && !v) return;
                var key = p.id + '-' + (v ? v.id : 0), line = this.shopCart.find(function (l) { return l.key === key; });
                if (line) line.qty++;
                else this.shopCart.push({ key: key, product_id: p.id, variant_id: v ? v.id : null, name: p.name, variantLabel: v ? v.label : '', price: v ? v.price : p.price, qty: 1, shipping: p.requires_shipping });
            },
            cartQty: function (i, d) { var l = this.shopCart[i]; l.qty += d; if (l.qty < 1) this.shopCart.splice(i, 1); },
            async createOrder() {
                var c = this.current; if (!c || this.shopBusy || !this.shopCart.length) return;
                var f = this.shopForm;
                if (this.cartNeedsShipping && (!f.addr1.trim() || !f.city.trim())) { this.notify('Estos productos requieren envío: completa dirección y ciudad.'); return; }
                if (!this.shop.customer.phone && !f.phone.trim()) { this.notify('Ingresa el teléfono del cliente.'); return; }
                this.shopBusy = true;
                try {
                    var body = {
                        items: this.shopCart.map(function (l) { return { product_id: l.product_id, variant_id: l.variant_id, quantity: l.qty }; }),
                        payment_gateway_id: f.gateway, notes: f.notes.trim() || null, notify_on_paid: f.notify,
                        shipping: this.cartNeedsShipping ? { address_line1: f.addr1.trim(), city: f.city.trim(), country_code: 'BO' } : null,
                        customer: f.phone.trim() ? { phone: f.phone.trim() } : null,
                    };
                    var res = await fetch(API + '/conversations/' + c.id + '/shop/order', { method: 'POST', headers: { Authorization: 'Bearer ' + this.token, Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
                    var json = await res.json().catch(function () { return {}; });
                    if (res.status === 401) { this.logout(false); return; }
                    if (!res.ok && res.status !== 207) throw { message: json.message || 'No se pudo crear el pedido.' };
                    this.shop = json.data; this.shopCart = []; f.notes = ''; f.addr1 = ''; f.city = '';
                    this.notify(res.status === 207 ? 'Pedido creado, pero no se pudo enviar: ' + json.data.send_error : 'Pedido creado y enviado al cliente', res.status === 207 ? 8000 : 3200);
                    this.loadMsgs(false); this.searchProducts();
                } catch (e) { this.notify(e.message, 8000); }
                finally { this.shopBusy = false; }
            },
            async orderAction(o, action, ok) {
                var c = this.current; if (!c || this.shopBusy) return;
                this.shopBusy = true;
                try { this.shop = await this.api('/conversations/' + c.id + '/shop/orders/' + o.id + '/' + action, { method: 'POST' }); this.notify(ok); this.loadMsgs(false); if (action === 'cancel') this.searchProducts(); }
                catch (e) { this.notify(e.message, 8000); }
                finally { this.shopBusy = false; }
            },
            resendOrder: function (o) { this.orderAction(o, 'resend', 'Pedido reenviado al cliente'); },
            cancelOrder: function (o) { if (confirm('¿Cancelar el pedido ' + o.number + '? Se devuelve el stock.')) this.orderAction(o, 'cancel', 'Pedido cancelado'); },
            orderLabel: function (st) { return { pending: 'Pendiente', awaiting_payment: 'Por pagar', paid: 'Pagado', fulfilled: 'Entregado', cancelled: 'Cancelado', refunded: 'Reembolsado' }[st] || st; },
            chargeLabel: function (st) { return { pending: 'Pendiente', paid: 'Pagado', expired: 'Vencido', cancelled: 'Cancelado' }[st] || st; },

            // ---------- presentación ----------
            get totalUnread() { return this.accounts.reduce(function (t, a) { return t + (a.unread || 0); }, 0); },
            get listTitle() { var a = this.accountId && this.accounts.find(function (x) { return x.id === this.accountId; }, this); return a ? a.label : (this.tenant ? this.tenant.name : 'Chats'); },
            get listSub() { var a = this.accountId && this.accounts.find(function (x) { return x.id === this.accountId; }, this); return a ? (PLATFORMS[a.platform] || a.platform) + (a.phone_number ? ' · ' + a.phone_number : '') : 'Todas las cuentas'; },
            get themeLabel() { return { auto: 'Automático (según tu dispositivo)', light: 'Claro', dark: 'Oscuro' }[this.theme]; },

            acctColor: function (a) { return PALETTE[a.id % PALETTE.length]; },
            acctLabel: function (id) { var a = this.accounts.find(function (x) { return x.id === id; }); return a ? a.label : ''; },
            cname: function (c) { var n = c.contact && c.contact.name; return n && !/^\d{9,}$/.test(n) ? n : ((c.contact && c.contact.phone) || 'Sin nombre'); },
            initials: function (s) { s = String(s || '').trim(); if (!s || /^[\d+]/.test(s)) return '#'; return s.split(/\s+/).map(function (w) { return w[0]; }).slice(0, 2).join('').toUpperCase(); },
            preview: function (c) { return c.last_message ? (c.last_message.direction === 'outbound' ? 'Tú: ' : '') + (c.last_message.body || 'Adjunto') : 'Sin mensajes'; },
            fmtTime: function (iso) {
                if (!iso) return ''; var d = new Date(iso), n = new Date();
                if (d.toDateString() === n.toDateString()) return d.toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' });
                var y = new Date(n); y.setDate(n.getDate() - 1);
                return d.toDateString() === y.toDateString() ? 'Ayer' : d.toLocaleDateString('es', { day: '2-digit', month: '2-digit' });
            },
            msgClass: function (m) { return m.kind === 'event' ? 'is-event' : m.kind === 'note' ? 'is-note' : m.direction === 'outbound' ? 'is-out' : 'is-in'; },
            eventText: function (m) {
                if (m.type && m.type !== 'delegated') return m.body || m.type;
                var by = m.by ? (m.by.id === this.user.id ? 'Tú' : m.by.name) : 'Alguien';
                var to = m.data && m.data.to ? (m.data.to.id === this.user.id ? 'ti' : m.data.to.name) : null;
                return by + (to ? ' delegó la conversación a ' + to : ' dejó la conversación sin asignar') + (m.body ? ' · “' + m.body + '”' : '');
            },
            autoGrow: function (el) { autoGrow(el); },
            scrollDown: function (force) {
                var self = this;
                this.$nextTick(function () {
                    var t = self.$refs.thread; if (!t) return;
                    if (force || t.scrollHeight - t.scrollTop - t.clientHeight < 140) t.scrollTop = t.scrollHeight;
                });
            },
            notify: function (text, ms) { var self = this; this.toast = text; clearTimeout(this._toastTimer); this._toastTimer = setTimeout(function () { self.toast = ''; }, ms || 3200); },

            cycleTheme: function () {
                this.theme = { auto: 'light', light: 'dark', dark: 'auto' }[this.theme];
                store('theme', this.theme);
                if (this.theme === 'auto') document.documentElement.removeAttribute('data-theme'); else document.documentElement.setAttribute('data-theme', this.theme);
            },
            async install() { if (!this.installPrompt) return; this.installPrompt.prompt(); await this.installPrompt.userChoice; this.installPrompt = null; this.sheet = ''; },
        };
    };

    function autoGrow(el) { el.style.height = 'auto'; el.style.height = Math.min(el.scrollHeight, 140) + 'px'; }
})();
