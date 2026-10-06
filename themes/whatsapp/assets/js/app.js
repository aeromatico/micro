/* PWA de chat — Alpine. Habla solo con /api/v1/chat (plugin aero/chat). */
(function () {
    'use strict';

    var API = '/api/v1/chat';
    var KEY = 'aero.chat.';
    var PALETTE = ['#17695a', '#b4532a', '#3b5fa8', '#8a3f7a', '#7a6a12', '#2f6f8f'];
    var PLATFORMS = { whatsapp: 'WhatsApp', facebook: 'Messenger', instagram: 'Instagram', telegram: 'Telegram', sms: 'SMS', livechat: 'Chat web' };
    var soundCache = {}, soundPrimed = {};
    function isHexColor(hex) { return /^#[0-9a-f]{6}$/i.test(hex || ''); }
    function getSound(a) {
        var audio = soundCache[a.id];
        if (!audio) { audio = new Audio(a.notification_sound); audio.preload = 'auto'; soundCache[a.id] = audio; }
        return audio;
    }
    function playAccountSound(a) {
        if (!a || !a.notification_sound) return;
        try { var audio = getSound(a); audio.pause(); audio.currentTime = 0; audio.play().catch(function () {}); } catch (e) {}
    }
    // Safari/iOS solo deja sonar un <audio> si ya se reprodujo una vez dentro de un gesto del
    // usuario (clic/toque); sin esto, el primer mensaje recién llegado nunca suena.
    function primeSounds(accounts) {
        (accounts || []).forEach(function (a) {
            if (!a.notification_sound || soundPrimed[a.id]) return;
            try {
                var audio = getSound(a), p = audio.play();
                if (p && p.then) p.then(function () { audio.pause(); audio.currentTime = 0; }).catch(function () {});
                soundPrimed[a.id] = true;
            } catch (e) {}
        });
    }

    function store(k, v) {
        try { if (v === undefined) return localStorage.getItem(KEY + k); if (v === null) localStorage.removeItem(KEY + k); else localStorage.setItem(KEY + k, v); } catch (e) { return null; }
    }

    // Pide almacenamiento persistente para que el navegador no borre el token por falta de espacio.
    try { if (navigator.storage && navigator.storage.persist) navigator.storage.persist(); } catch (e) { /* sin soporte */ }

    function jget(k) { try { return JSON.parse(store(k)); } catch (e) { return null; } }
    function jset(k, v) { try { store(k, JSON.stringify(v)); } catch (e) { /* cuota llena: se ignora */ } }

    window.chatApp = function () {
        return {
            screen: 'boot', pendingCode: '', handle: '', tenant: null, user: null, token: null,
            gateInput: '', gateError: '', loginForm: { login: '', password: '' }, loginError: '', busy: false,
            accounts: [], agents: [], convs: [], accountId: null, filter: 'all', q: '', loading: true,
            current: null, quick: [], msgs: [], draft: '', mode: 'reply', sheet: '', delegateNote: '', rowMenuConv: null,
            banForm: { hours: '' }, banBusy: false,
            lcSettings: { widget_mode: 'livechat', widget_whatsapp: '', custom_code: '', livechat_enabled: true, enabled: false, account_id: '', to: '' }, lcAccounts: [], lcBusy: false,
            crm: null, pay: null, shop: null, shopQ: '', shopResults: [], shopLoading: false, shopBusy: false, shopCart: [], shopForm: { gateway: null, notes: '', addr1: '', city: '', phone: '', notify: true }, payBusy: false, payForm: { amount: '', description: '', days: 1, bank: null, notify: true }, crmTab: 'contact', cform: { first_name: '', last_name: '', email: '' }, crmLoading: false, crmError: '', crmBusy: false, deptId: null,
            _allTabs: [{ id: 'contact', label: 'Contacto' }, { id: 'ticket', label: 'Ticket' }, { id: 'lead', label: 'Lead' }, { id: 'sale', label: 'Tienda' }, { id: 'pay', label: 'Cobro' }],
            ticketStatuses: [{ id: 'open', label: 'Abierto' }, { id: 'pending', label: 'En espera' }, { id: 'resolved', label: 'Resuelto' }, { id: 'closed', label: 'Cerrado' }],
            leadStatuses: [{ id: 'new', label: 'Nuevo' }, { id: 'contacted', label: 'Contactado' }, { id: 'qualified', label: 'Calificado' }, { id: 'disqualified', label: 'Descartado' }],
            newForm: { account: null, phone: '', name: '', body: '' }, newBusy: false, pollForm: { question: '', options: ['', ''], multiple: false }, pollBusy: false, geoState: 'unknown', attach: null, attachCaption: '', recording: false, recSecs: 0, _rec: null, _recTimer: null, geoBusy: false, cardBusy: 0, lightbox: '', slashIdx: 0, ticketSubject: '', toast: '', theme: 'auto', installPrompt: null, online: navigator.onLine, pushState: 'unsupported', unread: 0, replyTo: null,
            filters: [{ id: 'all', label: 'Todos' }, { id: 'mine', label: 'Míos' }, { id: 'free', label: 'Sin asignar' }, { id: 'unread', label: 'No leídos' }, { id: 'archived', label: 'Archivados' }],
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

                if ('serviceWorker' in navigator) {
                    navigator.serviceWorker.register('/sw.js').catch(function () {});
                    // Clic en un aviso push con la app ya abierta: abre el chat indicado (/{espacio}/{codigo}).
                    navigator.serviceWorker.addEventListener('message', function (e) {
                        var d = e.data || {}; if (d.type !== 'open-url' || !self.token) return;
                        var m = new URL(d.url, location.origin).pathname.split('/');
                        if ((m[1] || '').toLowerCase() === self.handle && m[2]) { self.pendingCode = m[2].toLowerCase(); self.openPending(); }
                    });
                }
                // Pide almacenamiento persistente: evita que el navegador borre la sesión y la caché por falta de espacio.
                if (navigator.storage && navigator.storage.persist) navigator.storage.persist().catch(function () {});

                var parts = location.pathname.split('/');
                var handle = (parts[1] || '').toLowerCase();
                this.pendingCode = (parts[2] || '').toLowerCase();
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
                document.title = 'Omnichat by Clouds · ' + (this.tenant ? this.tenant.name : handle);
                store('last', handle);
                this.token = store('token.' + handle);
                if (!this.token) { this.screen = 'login'; return; }
                try {
                    var me = await this.api('/me');
                    this.user = me.user; this.tenant = me.tenant;
                    jset('snap.' + handle + '.me', { user: me.user, tenant: me.tenant });
                    this.startInbox();
                } catch (e) {
                    if (e.status === 401 || e.status === 403) return;
                    // Sin red o servidor caído: la sesión sigue vigente, se abre con lo último guardado.
                    var snap = jget('snap.' + handle + '.me');
                    if (snap) { this.user = snap.user; this.tenant = snap.tenant; this.loadSnapshot(); this.startInbox(); }
                    else this.screen = 'login';
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
                    if (res.status === 403 && body.error === 'pro_required') { this.screen = 'upgrade'; return; }
                    if (!res.ok) throw new Error(res.status === 429 ? 'Demasiados intentos. Espera un minuto.' : (body.message || 'No se pudo iniciar sesión.'));
                    this.token = body.data.token; this.user = body.data.user; this.tenant = body.data.tenant;
                    store('token.' + this.handle, this.token);
                    this.loginForm = { login: '', password: '' };
                    this.startInbox();
                } catch (e) {
                    this.loginError = e.message;
                } finally { this.busy = false; }
            },

            // A la raíz (no al login del mismo espacio): en modo PWA instalado no hay
            // barra de direcciones, así que es la única forma intuitiva de cambiar
            // de cuenta. Se olvida el último espacio para que el gate no lo rellene solo.
            logout: function (remote) {
                if (remote && this.token) fetch(API + '/logout', { method: 'POST', headers: { Authorization: 'Bearer ' + this.token, Accept: 'application/json' } }).catch(function () {});
                store('token.' + this.handle, null);
                ['snap.' + this.handle + '.me', 'snap.' + this.handle + '.inbox'].concat((jget('msgidx.' + this.handle) || []).map(function (id) { return 'msgs.' + this.handle + '.' + id; }, this)).concat('msgidx.' + this.handle).forEach(function (k) { store(k, null); });
                store('last', null);
                location.href = '/';
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
                if (res.status === 403 && body.error === 'pro_required') { this.stopTimers(); this.screen = 'upgrade'; throw { status: 403, message: body.message }; }
                if (!res.ok) throw { status: res.status, message: body.message || 'Error inesperado.' };
                return body.data !== undefined && body.meta === undefined ? body.data : body;
            },

            // ---------- bandeja ----------
            startInbox: function () {
                var self = this;
                this.screen = 'inbox';
                this.refresh(true);
                this.openPending();
                this.pushInit(); this.geoInit();
                document.addEventListener('click', function () { primeSounds(self.accounts); });
                document.addEventListener('touchstart', function () { primeSounds(self.accounts); }, { passive: true });
                this.stopTimers();
                this._timers.push(setInterval(function () { if (!document.hidden) self.refresh(); }, 8000));
                this._timers.push(setInterval(function () { if (!document.hidden && self.current) self.loadMsgs(false); }, 4000));
            },
            // Enlace directo /{espacio}/{codigo}: abre esa conversación al entrar.
            async openPending() {
                var code = this.pendingCode; this.pendingCode = '';
                if (!code) return;
                if (!this.current) history.replaceState(null, '', '/' + this.handle);   // así "atrás" desde el chat vuelve a la lista
                try { await this.openConv(await this.api('/conversations/code/' + encodeURIComponent(code))); }
                catch (e) { if (e.status === 404) { this.notify('No encontramos esa conversación.'); history.replaceState(null, '', '/' + this.handle); } }
            },
            stopTimers: function () { this._timers.forEach(clearInterval); this._timers = []; },

            async refresh(first) {
                try {
                    var r = await Promise.all([this.api('/accounts'), this.api('/agents'), this.fetchConvs()]);
                    this.accounts = r[0]; this.agents = r[1];
                    if (this.accountId && this.isHidden(this.accountId)) this.accountId = null;   // el número se desconectó
                    this.applyConvs(r[2]);
                    if (!this.accountId && this.filter === 'all' && !this.q.trim()) jset('snap.' + this.handle + '.inbox', { accounts: r[0], agents: r[1], convs: r[2] });
                } catch (e) { /* el banner de conexión ya avisa */ }
                this.loading = false;
            },
            loadSnapshot: function () {
                var snap = jget('snap.' + this.handle + '.inbox');
                if (snap) { this.accounts = snap.accounts || []; this.agents = snap.agents || []; this.convs = (snap.convs && snap.convs.data) || []; }
                this.loading = false;
            },
            cacheMsgs: function (id, rows) {
                var idx = jget('msgidx.' + this.handle) || [];
                idx = idx.filter(function (x) { return x !== id; }); idx.unshift(id);
                idx.slice(15).forEach(function (old) { store('msgs.' + this.handle + '.' + old, null); }, this);
                jset('msgidx.' + this.handle, idx.slice(0, 15));
                jset('msgs.' + this.handle + '.' + id, rows.slice(-80));
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
                this.convs = (res.data || []).filter(function (c) { return !this.isHidden(c.account_id); }, this);
                if (this.current) {
                    var fresh = this.convs.find(function (c) { return c.id === this.current.id; }, this);
                    if (fresh) this.current = Object.assign(this.current, fresh, { unread_count: 0 });
                }
            },
            // Solo se muestran los números conectados (rail, lista de chats y "Nuevo chat").
            isOffline: function (a) { return !!a.status && ['connected', 'active'].indexOf(a.status) < 0; },
            isHidden: function (accountId) { var a = this.accounts.find(function (x) { return x.id === accountId; }); return !!a && this.isOffline(a); },
            // Lo más reciente arriba, entrante o saliente; se recalcula solo al enviar o llegar un mensaje.
            get sortedConvs() {
                var t = function (c) { return c.last_message_at ? Date.parse(c.last_message_at) || 0 : 0; };
                return this.convs.slice().sort(function (a, b) { return t(b) - t(a) || b.id - a.id; });
            },
            get visibleAccounts() { return this.accounts.filter(function (a) { return !this.isOffline(a); }, this); },
            pickAccount: function (id) { this.accountId = id; this.loading = true; this.loadConvs(); },
            setFilter: function (id) { this.filter = id; this.loading = true; this.loadConvs(); },

            // ---------- conversación ----------
            async openConv(c) {
                var wasOpen = this.current;
                this.current = c; this.msgs = []; this.draft = ''; this.mode = 'reply'; this.crm = null; this.ticketSubject = '';
                this.pay = null; this.shop = null; this.shopCart = []; this.shopResults = [];
                if (this.sheet === 'crm') { this.loadCrm(); this.loadPay(); this.loadShop(); }
                var url = c.code ? '/' + this.handle + '/' + c.code : location.pathname;
                if (!wasOpen && history.state && history.state.chat) history.replaceState({ chat: c.id }, '', url);
                else if (!wasOpen) history.pushState({ chat: c.id }, '', url);
                else history.replaceState({ chat: c.id }, '', url);
                this.loadQuick();
                await this.loadMsgs(true);
                if (c.unread_count > 0) {
                    c.unread_count = 0;
                    this.api('/conversations/' + c.id + '/read', { method: 'POST' }).then(this.refreshAccounts.bind(this)).catch(function () {});
                }
            },
            closeChat: function () {
                var self = this; this.sheet = '';
                if (this.recording) this.stopRec(false);
                if (history.state && history.state.chat) { history.back(); setTimeout(function () { if (self.current) self.current = null; }, 200); }
                else { this.current = null; if (location.pathname !== '/' + this.handle) history.replaceState(null, '', '/' + this.handle); }
            },
            // ---------- respuestas rápidas ("/") ----------
            async loadQuick() { try { this.quick = await this.api('/quick-replies'); } catch (e) {} },
            get topQuick() {
                return this.quick.slice().sort(function (a, b) { return b.uses_count - a.uses_count || a.sort_order - b.sort_order; }).slice(0, 100);
            },
            get slashItems() {
                var m = /^\/([^\s]*)$/.exec(this.draft); if (!m) return [];
                var t = m[1].toLowerCase();
                return this.quick.filter(function (q) { return !t || q.shortcut.indexOf(t) === 0 || q.title.toLowerCase().indexOf(t) >= 0; }).slice(0, 8);
            },
            get slashOpen() { return this.slashItems.length > 0; },
            slashKey: function (e) {
                if (!this.slashOpen) return;
                var n = this.slashItems.length;
                if (e.key === 'ArrowDown') { e.preventDefault(); this.slashIdx = (this.slashIdx + 1) % n; }
                else if (e.key === 'ArrowUp') { e.preventDefault(); this.slashIdx = (this.slashIdx + n - 1) % n; }
                else if (e.key === 'Enter' || e.key === 'Tab') { e.preventDefault(); this.pickQuick(this.slashItems[this.slashIdx]); }
                else if (e.key === 'Escape') { e.preventDefault(); this.draft = ''; }
            },
            pickQuick: function (q) {
                if (!q) return;
                var d = this.draft.trim();
                this.draft = (d && d.charAt(0) !== '/') ? d + '\n' + q.body : q.body;
                this.slashIdx = 0; q.uses_count++;
                this.api('/quick-replies/' + q.id + '/use', { method: 'POST' }).catch(function () {});
                var self = this;
                this.$nextTick(function () { var ta = document.querySelector('.composer textarea'); if (ta) { autoGrow(ta); ta.focus(); } });
            },

            // ---------- nuevo chat ----------
            // Un chat web no tiene "número" al que escribirle en frío: el visitante siempre abre él el chat desde el widget.
            get coldStartAccounts() { return this.visibleAccounts.filter(function (a) { return a.platform !== 'livechat'; }); },
            openNew: function () {
                var pool = this.coldStartAccounts;
                if (!pool.length) { this.notify('No hay un canal desde el que iniciar chats nuevos.'); return; }
                var acc = pool.find(function (a) { return a.id === this.accountId; }, this) || pool[0];
                this.newForm = { account: acc.id, phone: '', name: '', body: '' };
                this.sheet = 'new';
            },
            async startChat() {
                var f = this.newForm; if (this.newBusy) return;
                if (!f.account) { this.notify('No hay un número conectado desde el que escribir.'); return; }
                if (f.phone.replace(/\D/g, '').length < 8) { this.notify('Ingresa el número con código de país, ej. 591 7xxxxxxx.'); return; }
                if (!f.body.trim()) { this.notify('Escribe el primer mensaje.'); return; }
                this.newBusy = true;
                try {
                    var c = await this.api('/conversations/new', { method: 'POST', body: { account_id: f.account, phone: f.phone, name: f.name.trim() || null, body: f.body.trim() } });
                    this.sheet = '';
                    var known = this.convs.find(function (x) { return x.id === c.id; });
                    if (!known) this.convs.unshift(c);
                    this.openConv(known || c);
                    this.refreshAccounts();
                } catch (e) { this.notify(e.message, 8000); }
                finally { this.newBusy = false; }
            },

            // ---------- tarjeta de producto ----------
            async sendCard(p) {
                var c = this.current; if (!c || this.cardBusy) return;
                this.cardBusy = p.id;
                try {
                    await this.api('/conversations/' + c.id + '/shop/products/' + p.id + '/card', { method: 'POST' });
                    this.notify('Tarjeta de ' + p.name + ' enviada'); this.loadMsgs(false);
                } catch (e) { this.notify(e.message, 8000); }
                finally { this.cardBusy = 0; }
            },

            // ---------- adjuntos y audio ----------
            kindOf: function (mime) { mime = String(mime || ''); return mime.indexOf('image/') === 0 ? 'image' : mime.indexOf('video/') === 0 ? 'video' : mime.indexOf('audio/') === 0 ? 'audio' : 'document'; },
            pickFile: function (ev) {
                var f = ev.target.files && ev.target.files[0]; ev.target.value = '';
                if (!f) return;
                if (f.size > 16 * 1024 * 1024) { this.notify('El archivo pesa más de 16 MB.'); return; }
                var kind = this.kindOf(f.type);
                this.attach = { file: f, kind: kind, name: f.name, url: kind === 'image' || kind === 'video' ? URL.createObjectURL(f) : '' };
                this.attachCaption = ''; this.sheet = 'attach';
            },
            cancelAttach: function () { if (this.attach && this.attach.url) URL.revokeObjectURL(this.attach.url); this.attach = null; this.sheet = ''; },
            async sendAttachment() {
                var a = this.attach; if (!a) return;
                var caption = this.attachCaption.trim();
                this.attach = null; this.sheet = '';
                await this.uploadMedia(a.file, a.file.name, a.kind, caption, a.url || URL.createObjectURL(a.file));
            },
            async uploadMedia(blob, name, kind, caption, localUrl, voice) {
                var c = this.current; if (!c) return;
                var tmp = 'tmp' + (++this._seq);
                var item = { kind: 'message', id: tmp, direction: 'outbound', body: caption, media_url: localUrl, media_type: kind, file_name: name, status: 'sending', at: new Date().toISOString() };
                this.msgs.push(item); this.scrollDown(true);
                var form = new FormData(); form.append('file', blob, name); if (caption) form.append('caption', caption); if (voice) form.append('voice', '1');
                try {
                    var res = await fetch(API + '/conversations/' + c.id + '/attachment', { method: 'POST', headers: { Authorization: 'Bearer ' + this.token, Accept: 'application/json' }, body: form });
                    var json = await res.json().catch(function () { return {}; });
                    if (res.status === 401) { this.logout(false); return; }
                    if (!res.ok) throw { message: json.message || (res.status === 413 ? 'El archivo es demasiado grande.' : 'No se pudo enviar.') };
                    var i = this.msgs.findIndex(function (m) { return m.id === tmp; });
                    if (i >= 0) this.msgs.splice(i, 1, Object.assign(json.data, { local_url: localUrl }));
                    c.last_message = { body: caption, direction: 'outbound', media_type: kind }; c.last_message_at = new Date().toISOString();
                    if (!c.assigned_to) c.assigned_to = this.user;
                    this.spendCredit();
                } catch (e) { item.status = 'failed'; item.failed_reason = e.message; this.notify('No se envió: ' + e.message, 8000); }
            },
            async startRec() {
                if (this.recording || !this.current) return;
                if (!navigator.mediaDevices || !window.MediaRecorder) { this.notify('Este navegador no puede grabar audio.'); return; }
                try {
                    var stream = await navigator.mediaDevices.getUserMedia({ audio: true }), self = this;
                    var mime = ['audio/ogg;codecs=opus', 'audio/webm;codecs=opus', 'audio/mp4', 'audio/webm'].find(function (t) { return MediaRecorder.isTypeSupported(t); }) || '';
                    var rec = new MediaRecorder(stream, mime ? { mimeType: mime } : undefined), chunks = [];
                    rec.ondataavailable = function (e) { if (e.data && e.data.size) chunks.push(e.data); };
                    rec.onstop = function () {
                        stream.getTracks().forEach(function (t) { t.stop(); });
                        if (!rec._send) return;
                        var type = (rec.mimeType || mime || 'audio/webm').split(';')[0], ext = type.indexOf('ogg') >= 0 ? 'ogg' : type.indexOf('mp4') >= 0 ? 'm4a' : 'webm';
                        var blob = new Blob(chunks, { type: type });
                        if (blob.size < 800) { self.notify('La grabación quedó vacía.'); return; }
                        self.uploadMedia(blob, 'audio.' + ext, 'audio', '', URL.createObjectURL(blob), true);
                    };
                    rec.start(); this._rec = rec; this.recording = true; this.recSecs = 0;
                    this._recTimer = setInterval(function () { self.recSecs++; if (self.recSecs >= 300) self.stopRec(true); }, 1000);
                } catch (e) { this.notify(e && e.name === 'NotAllowedError' ? 'Permiso de micrófono denegado: actívalo en los ajustes del sitio.' : 'No se pudo acceder al micrófono.', 6000); }
            },
            stopRec: function (send) {
                clearInterval(this._recTimer); this.recording = false;
                var r = this._rec; this._rec = null;
                if (r && r.state !== 'inactive') { r._send = !!send; r.stop(); }
            },
            recTime: function () { var s = this.recSecs; return Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2); },

            // ---------- distinguir el canal activo (color + sonido de la cuenta) ----------
            get currentAccount() {
                return this.current && this.accounts.find(function (x) { return x.id === this.current.account_id; }, this);
            },
            get chatBarTint() {
                var c = this.currentAccount && this.currentAccount.color;
                if (!isHexColor(c)) return '';
                return 'background:color-mix(in srgb,' + c + ' 20%,transparent);backdrop-filter:blur(14px) saturate(1.6);-webkit-backdrop-filter:blur(14px) saturate(1.6);border-bottom-color:' + c + ';border-bottom-width:3px';
            },
            get chatAvatarTint() {
                var c = this.currentAccount && this.currentAccount.color;
                return isHexColor(c) ? ('background:' + c + ';color:#fff') : '';
            },

            // ---------- ubicación del operador ----------
            get canLocate() {
                var a = this.current && this.accounts.find(function (x) { return x.id === this.current.account_id; }, this);
                // El backend decide: pin nativo si la cuenta lo admite, si no, enlace de mapa en texto.
                return !!a;
            },
            position: function () {
                return new Promise(function (resolve, reject) {
                    if (!navigator.geolocation) return reject({ code: 0, message: 'Este dispositivo no permite obtener la ubicación.' });
                    navigator.geolocation.getCurrentPosition(resolve, reject, { enableHighAccuracy: true, timeout: 15000, maximumAge: 30000 });
                });
            },
            geoError: function (e) {
                if (e && e.code === 1) { this.geoState = 'denied'; return 'Permiso de ubicación denegado: actívalo en los ajustes del sitio.'; }
                return (e && e.code === 3) ? 'No se pudo obtener la ubicación a tiempo.' : ((e && e.message) || 'No se pudo obtener la ubicación.');
            },
            // Pide el permiso (el navegador muestra su aviso la primera vez).
            async askGeo() {
                if (this.geoState === 'denied') { this.notify('Bloqueada en el navegador: actívala en los ajustes del sitio.'); return; }
                try { await this.position(); this.geoState = 'granted'; this.notify('Ubicación activada'); }
                catch (e) { this.notify(this.geoError(e), 6000); }
            },
            async geoInit() {
                try { if (navigator.permissions) { var p = await navigator.permissions.query({ name: 'geolocation' }), self = this; this.geoState = p.state; p.onchange = function () { self.geoState = p.state; }; } } catch (e) {}
            },
            async sendLocation() {
                var c = this.current; if (!c || this.geoBusy) return;
                this.geoBusy = true;
                try {
                    var pos = await this.position(); this.geoState = 'granted';
                    var res = await this.api('/conversations/' + c.id + '/location', { method: 'POST', body: { latitude: +pos.coords.latitude.toFixed(6), longitude: +pos.coords.longitude.toFixed(6) } });
                    this.msgs.push(res); this.scrollDown(true);
                    c.last_message = { body: res.body, direction: 'outbound' }; c.last_message_at = res.at;
                    if (!c.assigned_to) c.assigned_to = this.user;
                    this.spendCredit();
                } catch (e) { this.notify(e && e.status ? e.message : this.geoError(e), 7000); }
                finally { this.geoBusy = false; }
            },
            mapLink: function (m) {
                var r = m.type === 'location' && /(-?\d+\.\d+),\s*(-?\d+\.\d+)/.exec(m.body || '');
                return r ? 'https://www.google.com/maps?q=' + r[1] + ',' + r[2] : '';
            },

            // ---------- citar mensajes (solo cuentas con capacidad quote_reply: WhatsApp Web) ----------
            get canQuote() {
                var a = this.current && this.accounts.find(function (x) { return x.id === this.current.account_id; }, this);
                return !!(a && a.capabilities && a.capabilities.quote_reply);
            },
            get activeQuote() { return this.replyTo && this.current && this.replyTo.conv === this.current.id && this.mode === 'reply' ? this.replyTo.m : null; },
            quoteLabel: function (q) { return (q.direction === 'outbound' ? 'Tú' : 'Cliente') + ': ' + (q.body || '[adjunto]'); },
            setReply: function (m) {
                this.mode = 'reply'; this.replyTo = { conv: this.current.id, m: { id: m.id, direction: m.direction, body: m.body || (m.media_type ? '[' + m.media_type + ']' : '') } };
                var ta = document.querySelector('.composer textarea'); if (ta) ta.focus();
            },

            // ---------- Finalizar / Banear (solo canal de chat web) ----------
            get isLivechatChat() {
                var a = this.current && this.accounts.find(function (x) { return x.id === this.current.account_id; }, this);
                return !!(a && a.platform === 'livechat');
            },
            async finishLivechat() {
                var c = this.current; if (!c || !confirm('¿Finalizar esta conversación de chat web?')) return;
                try {
                    await this.api('/livechat/conversations/' + c.id + '/finish', { method: 'POST' });
                    this.notify('Conversación finalizada'); this.loadMsgs(false);
                } catch (e) { this.notify(e.message); }
            },
            get hasLivechat() { return this.accounts.some(function (a) { return a.platform === 'livechat'; }); },
            async openLcSettings() {
                try {
                    var d = await this.api('/livechat/settings');
                    this.lcAccounts = d.accounts || [];
                    this.lcSettings = { widget_mode: d.settings.widget_mode || 'livechat', widget_whatsapp: d.settings.widget_whatsapp || '', custom_code: d.settings.custom_code || '', livechat_enabled: d.settings.livechat_enabled !== false, enabled: !!d.settings.enabled, account_id: d.settings.account_id || '', to: d.settings.to || '' };
                    this.sheet = 'lcsettings';
                } catch (e) { this.notify(e.status === 403 ? 'No tienes permiso para configurar esto.' : e.message); }
            },
            async saveLcSettings() {
                if (this.lcBusy) return;
                this.lcBusy = true;
                try {
                    var d = await this.api('/livechat/settings', { method: 'POST', body: { widget_mode: this.lcSettings.widget_mode, widget_whatsapp: this.lcSettings.widget_whatsapp, custom_code: this.lcSettings.custom_code, livechat_enabled: this.lcSettings.livechat_enabled, enabled: this.lcSettings.enabled, account_id: this.lcSettings.account_id || null, to: this.lcSettings.to } });
                    this.lcSettings = { widget_mode: d.settings.widget_mode || 'livechat', widget_whatsapp: d.settings.widget_whatsapp || '', custom_code: d.settings.custom_code || '', livechat_enabled: d.settings.livechat_enabled !== false, enabled: !!d.settings.enabled, account_id: d.settings.account_id || '', to: d.settings.to || '' };
                    this.notify(!d.settings.livechat_enabled ? 'Livechat apagado' : (d.settings.enabled ? 'Livechat activo con retransmisión a WhatsApp' : 'Livechat activo'));
                    this.sheet = '';
                } catch (e) { this.notify(e.message); }
                finally { this.lcBusy = false; }
            },
            openBan: function () { this.banForm = { hours: '' }; this.sheet = 'ban'; },
            async submitBan(permanent) {
                var c = this.current; if (!c || this.banBusy) return;
                var body = {};
                if (!permanent) {
                    var hours = parseInt(this.banForm.hours, 10);
                    if (!(hours > 0)) { this.notify('Ingresa un número de horas válido.'); return; }
                    body.hours = hours;
                }
                this.banBusy = true;
                try {
                    await this.api('/livechat/conversations/' + c.id + '/ban', { method: 'POST', body: body });
                    this.notify(permanent ? 'Visitante baneado de forma permanente' : 'Visitante baneado por ' + body.hours + ' h');
                    this.sheet = ''; this.loadMsgs(false);
                } catch (e) { this.notify(e.message); }
                finally { this.banBusy = false; }
            },

            // ---------- emojis (solo escritorio: el celular ya trae su propio selector) ----------
            // No cargamos un picker propio a propósito — mostramos el atajo nativo del
            // sistema para acostumbrar al equipo a usarlo (Win/Mac/Linux ya traen uno bueno).
            get emojiShortcutLabel() {
                var p = ((navigator.userAgentData && navigator.userAgentData.platform) || navigator.platform || navigator.userAgent || '').toLowerCase();
                if (p.indexOf('mac') >= 0) return 'Cmd + Ctrl + Barra espaciadora';
                if (p.indexOf('win') >= 0) return 'Tecla Windows + . (punto)';
                if (p.indexOf('linux') >= 0 || p.indexOf('x11') >= 0) return 'Depende del entorno: con IBus activo, Ctrl + . abre el panel (activalo con "ibus-setup"). XFCE no lo trae por defecto.';
                return 'Buscá "insertar emoji" en los atajos de tu sistema';
            },
            showEmojiHint: function () { this.notify('😀 Emojis: ' + this.emojiShortcutLabel, 7000); },

            // ---------- encuestas (solo WhatsApp Web) ----------
            get canPoll() {
                var a = this.current && this.accounts.find(function (x) { return x.id === this.current.account_id; }, this);
                return !!(a && a.capabilities && a.capabilities.poll);
            },
            openPoll: function () { this.pollForm = { question: '', options: ['', ''], multiple: false }; this.sheet = 'poll'; },
            addPollOption: function () { if (this.pollForm.options.length < 12) this.pollForm.options.push(''); },
            removePollOption: function (i) { if (this.pollForm.options.length > 2) this.pollForm.options.splice(i, 1); },
            async sendPoll() {
                var c = this.current, f = this.pollForm; if (!c || this.pollBusy) return;
                var opts = f.options.map(function (o) { return o.trim(); }).filter(Boolean);
                if (!f.question.trim()) { this.notify('Escribe la pregunta.'); return; }
                if (opts.length < 2) { this.notify('Agrega al menos 2 opciones.'); return; }
                this.pollBusy = true;
                try {
                    var res = await this.api('/conversations/' + c.id + '/poll', { method: 'POST', body: { question: f.question.trim(), options: opts, multiple: f.multiple } });
                    this.msgs.push(res); this.sheet = ''; this.scrollDown(true);
                    c.last_message = { body: res.body, direction: 'outbound' }; c.last_message_at = res.at;
                    if (!c.assigned_to) c.assigned_to = this.user;
                    this.spendCredit();
                } catch (e) { this.notify(e.message, 8000); }
                finally { this.pollBusy = false; }
            },

            async refreshAccounts() { try { this.accounts = await this.api('/accounts'); } catch (e) {} },

            tickOf(m) { return m.status === 'queued' ? ' 🕓' : m.status === 'sent' ? ' ✓' : ' ✓✓'; },
            tickTitle(m) { return { queued: 'En cola', sent: 'Enviado', delivered: 'Entregado', read: 'Leído' }[m.status] || ''; },
            async loadMsgs(initial) {
                var c = this.current; if (!c) return;
                var path = '/conversations/' + c.id + '/messages';
                var pending = false;
                if (!initial) {
                    // Mientras haya salientes sin leer/fallar se relee la ventana reciente para refrescar sus ticks.
                    pending = this.msgs.some(function (m) { return m.direction === 'outbound' && ['queued', 'sent', 'delivered'].indexOf(m.status) >= 0; });
                    var last = this.msgs.filter(function (m) { return !String(m.id).startsWith('tmp'); }).pop();
                    if (last && !pending) path += '?after=' + encodeURIComponent(last.at);
                }
                try {
                    var rows = await this.api(path);
                    if (!this.current || this.current.id !== c.id) return;
                    if (initial) { this.msgs = rows; this.cacheMsgs(c.id, rows); this.scrollDown(true); return; }
                    var known = {}; this.msgs.forEach(function (m) { known[m.id] = m; });
                    if (pending) rows.forEach(function (r) { var k = known[r.id]; if (k && (k.status !== r.status || k.failed_reason !== r.failed_reason)) { k.status = r.status; k.failed_reason = r.failed_reason; } });
                    var fresh = rows.filter(function (m) { return !known[m.id]; });
                    if (fresh.length) {
                        this.msgs = this.msgs.concat(fresh); this.scrollDown(false);
                        if (fresh.some(function (m) { return m.direction === 'inbound'; })) {
                            if (!document.hidden) {
                                c.unread_count = 0;
                                this.api('/conversations/' + c.id + '/read', { method: 'POST' }).then(this.refreshAccounts.bind(this)).catch(function () {});
                                playAccountSound(this.currentAccount);
                            }
                        }
                        var paid = fresh.find(function (m) { return m.type === 'payment'; });
                        if (paid) { this.notify(paid.body); if (this.sheet === 'crm') { this.loadPay(); this.loadShop(); } }
                        this.cacheMsgs(c.id, this.msgs.filter(function (m) { return !String(m.id).startsWith('tmp'); }));
                    }
                } catch (e) {
                    if (initial && e.status === 0 && this.current && this.current.id === c.id) {
                        var cached = jget('msgs.' + this.handle + '.' + c.id);
                        if (cached) { this.msgs = cached; this.scrollDown(true); }
                    }
                }
            },

            async send() {
                var text = this.draft.trim(); if (!text || !this.current) return;
                var c = this.current, isNote = this.mode === 'note', tmp = 'tmp' + (++this._seq);
                var quote = isNote ? null : this.activeQuote; this.replyTo = null;
                this.draft = ''; this.$nextTick(function () { var ta = document.querySelector('.composer textarea'); if (ta) autoGrow(ta); });
                var item = isNote
                    ? { kind: 'note', id: tmp, body: text, by: this.user, at: new Date().toISOString() }
                    : { kind: 'message', id: tmp, direction: 'outbound', body: text, status: 'sending', quote: quote, at: new Date().toISOString() };
                this.msgs.push(item); this.scrollDown(true);
                try {
                    var res = await this.api('/conversations/' + c.id + (isNote ? '/note' : '/reply'), { method: 'POST', body: quote ? { body: text, reply_to: parseInt(String(quote.id).replace('m', ''), 10) } : { body: text } });
                    var i = this.msgs.findIndex(function (m) { return m.id === tmp; });
                    if (i >= 0) this.msgs.splice(i, 1, isNote ? Object.assign(item, { id: res.id }) : res);
                    if (!isNote) {
                        c.last_message = { body: text, direction: 'outbound' }; c.last_message_at = new Date().toISOString();
                        if (!c.assigned_to) c.assigned_to = this.user;
                        this.spendCredit();
                    }
                } catch (e) {
                    if (isNote) { this.msgs = this.msgs.filter(function (m) { return m.id !== tmp; }); this.draft = text; }
                    else { item.status = 'failed'; item.failed_reason = e.message; }
                    this.notify('No se envió: ' + e.message, 8000);
                }
            },

            /**
             * Descuenta 1 del contador visible tras un envío que cobra. Nada más: el
             * saldo real lo lleva el servidor. Reasigna el objeto (en vez de mutar
             * `.reaches` en el objeto anidado) para que Alpine SIEMPRE detecte el
             * cambio, sin depender de cómo haya quedado envuelto el objeto que vino
             * del JSON de /me o /login.
             */
            spendCredit() {
                var c = this.tenant && this.tenant.credits;
                if (c && c.reaches > 0) this.tenant.credits = Object.assign({}, c, { reaches: c.reaches - 1 });
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

            // ---------- archivar y silenciar ----------
            openRowMenu: function (c) { this.rowMenuConv = c; this.sheet = 'rowMenu'; },
            async toggleArchive(c) {
                if (!c) return;
                var archived = !c.is_archived;
                try {
                    var res = await this.api('/conversations/' + c.id + '/archive', { method: 'POST', body: { archived: archived } });
                    c.is_archived = res.is_archived; c.is_muted = res.is_muted;
                    this.sheet = ''; this.rowMenuConv = null;
                    if ((this.filter === 'archived') === !c.is_archived) this.convs = this.convs.filter(function (x) { return x.id !== c.id; });
                    this.notify(archived ? 'Chat archivado y silenciado' : 'Chat desarchivado');
                } catch (e) { this.notify(e.message); }
            },
            async toggleMute(c) {
                if (!c || c.is_archived) return;
                var muted = !c.is_muted;
                try {
                    var res = await this.api('/conversations/' + c.id + '/mute', { method: 'POST', body: { muted: muted } });
                    c.is_muted = res.is_muted;
                    this.sheet = ''; this.rowMenuConv = null;
                    this.notify(muted ? 'Chat silenciado' : 'Notificaciones reactivadas');
                } catch (e) { this.notify(e.message); }
            },


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
                var keep = this.contactDirty && this.crm && data && data.contact && this.crm.contact.id === data.contact.id;
                this.crm = data;
                var ct = data && data.contact;
                if (!keep) this.cform = { first_name: (ct && ct.first_name) || '', last_name: (ct && ct.last_name) || '', email: (ct && ct.email) || '' };
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
            async saveContact() { if (await this.crmCall('/contact', this.cform)) this.notify('Contacto actualizado'); },
            get contactDirty() {
                var ct = this.crm && this.crm.contact; if (!ct) return false;
                return (ct.first_name || '') !== this.cform.first_name.trim() || (ct.last_name || '') !== this.cform.last_name.trim() || (ct.email || '') !== this.cform.email.trim();
            },
            toggleList: function (l) { this.crmCall('/lists', { list_id: l.id, on: !(this.crm.list_ids || []).includes(l.id) }); },
            async createTicket() {
                var subject = this.ticketSubject.trim();
                if (await this.crmCall('/ticket', { department_id: this.deptId, subject: subject || null })) { this.ticketSubject = ''; this.notify('Ticket creado'); }
            },
            setTicketStatus: function (st) { this.crmCall('/ticket/' + this.crm.ticket.id, { status: st }); },
            setLead: function (st) { this.crmCall('/lead', st ? { status: st } : {}); },
            async createDeal() { if (await this.crmCall('/deal/create', {})) this.notify('Negocio creado'); },
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
            locLabel: function (l) {
                var d = new Date(l.at), t = isNaN(d) ? '' : d.toLocaleDateString('es', { day: 'numeric', month: 'short' }) + ' ' + d.toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' }) + ' · ';
                return t + (l.label ? l.label + ' · ' : '') + l.lat.toFixed(5) + ', ' + l.lng.toFixed(5);
            },
            // Solo la última ubicación compartida (la lista llega de la más reciente a la más antigua).
            get latestLoc() { return (this.shop && this.shop.locations || [])[0] || null; },
            // Un pedido tiene una sola dirección: usa la ubicación si algún producto del carrito la marcó.
            get cartUsesLoc() { return this.shopCart.some(function (l) { return l.useLoc; }); },
            get chosenLoc() { return this.cartUsesLoc ? this.latestLoc : null; },
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
                    // Sin ubicación compartida no hay interruptor: se apaga.
                } catch (e) {}
            },
            async searchProducts() {
                this.shopLoading = true;
                try {
                    var r = await this.api('/shop/products?q=' + encodeURIComponent(this.shopQ.trim()));
                    this.shopResults = (r.data || []).map(function (p) { p._variant = 0; p._useLoc = false; return p; });
                } catch (e) {}
                this.shopLoading = false;
            },
            addToCart: function (p) {
                var v = p.has_variants ? p.variants.find(function (x) { return x.id === p._variant; }) : null;
                if (p.has_variants && !v) return;
                var key = p.id + '-' + (v ? v.id : 0), line = this.shopCart.find(function (l) { return l.key === key; });
                if (line) line.qty++;
                if (line) line.useLoc = line.useLoc || !!p._useLoc;
                else this.shopCart.push({ key: key, product_id: p.id, variant_id: v ? v.id : null, name: p.name, variantLabel: v ? v.label : '', price: v ? v.price : p.price, qty: 1, shipping: p.requires_shipping, useLoc: !!p._useLoc });
            },
            cartQty: function (i, d) { var l = this.shopCart[i]; l.qty += d; if (l.qty < 1) this.shopCart.splice(i, 1); },
            // Con la ubicación compartida, sus coordenadas tienen prioridad; dirección y ciudad solo se envían si se escribieron.
            shippingPayload: function () {
                var f = this.shopForm, loc = this.chosenLoc, s = { country_code: 'BO' };
                if (f.addr1.trim()) s.address_line1 = f.addr1.trim();
                // Con ubicación no se pide ciudad: la dirección queda solo como aclaración.
                if (!loc && f.city.trim()) s.city = f.city.trim();
                if (loc) Object.assign(s, { latitude: loc.lat, longitude: loc.lng, location_label: loc.label });
                return s;
            },
            async createOrder() {
                var c = this.current; if (!c || this.shopBusy || !this.shopCart.length) return;
                var f = this.shopForm;
                if (this.cartNeedsShipping && !this.chosenLoc && (!f.addr1.trim() || !f.city.trim())) { this.notify('Estos productos requieren envío: completa dirección y ciudad, o usa la ubicación compartida.'); return; }
                if (!this.shop.customer.phone && !f.phone.trim()) { this.notify('Ingresa el teléfono del cliente.'); return; }
                this.shopBusy = true;
                try {
                    var body = {
                        items: this.shopCart.map(function (l) { return { product_id: l.product_id, variant_id: l.variant_id, quantity: l.qty }; }),
                        payment_gateway_id: f.gateway, notes: f.notes.trim() || null, notify_on_paid: f.notify,
                        shipping: this.cartNeedsShipping ? this.shippingPayload() : null,
                        customer: f.phone.trim() ? { phone: f.phone.trim() } : null,
                    };
                    var res = await fetch(API + '/conversations/' + c.id + '/shop/order', { method: 'POST', headers: { Authorization: 'Bearer ' + this.token, Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
                    var json = await res.json().catch(function () { return {}; });
                    if (res.status === 401) { this.logout(false); return; }
                    if (!res.ok && res.status !== 207) throw { message: json.message || 'No se pudo crear el pedido.' };
                    this.shop = json.data; this.shopCart = []; f.notes = ''; f.addr1 = ''; f.city = ''; this.shopResults.forEach(function (p) { p._useLoc = false; });
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
            get totalUnread() { return this.visibleAccounts.reduce(function (t, a) { return t + (a.unread || 0); }, 0); },
            get listTitle() { var a = this.accountId && this.accounts.find(function (x) { return x.id === this.accountId; }, this); return a ? a.label : (this.tenant ? this.tenant.name : 'Chats'); },
            get listSub() { var a = this.accountId && this.accounts.find(function (x) { return x.id === this.accountId; }, this); return a ? (PLATFORMS[a.platform] || a.platform) + (a.phone_number ? ' · ' + a.phone_number : '') : 'Todas las cuentas'; },
            get themeLabel() { return { auto: 'Automático (según tu dispositivo)', light: 'Claro', dark: 'Oscuro' }[this.theme]; },

            acctColor: function (a) { return isHexColor(a.color) ? a.color : PALETTE[a.id % PALETTE.length]; },
            acctAbbr: function (a) { return (a.abbreviation && a.abbreviation.trim()) || this.initials(a.label); },
            acctLabel: function (id) { var a = this.accounts.find(function (x) { return x.id === id; }); return a ? a.label : ''; },
            acctPlatform: function (id) { var a = this.accounts.find(function (x) { return x.id === id; }); return a ? a.platform : ''; },
            platformLabel: function (p) { return PLATFORMS[p] || p; },
            cname: function (c) { var n = c.contact && c.contact.name; return n && !/^\d{9,}$/.test(n) ? n : ((c.contact && c.contact.phone) || 'Sin nombre'); },
            initials: function (s) { s = String(s || '').trim(); if (!s || /^[\d+]/.test(s)) return '#'; return s.split(/\s+/).map(function (w) { return w[0]; }).slice(0, 2).join('').toUpperCase(); },
            preview: function (c) {
                var l = c.last_message; if (!l) return 'Sin mensajes';
                var kinds = { sticker: '🩹 Sticker', image: '📷 Foto', video: '🎥 Video', audio: '🎤 Audio' };
                return (l.direction === 'outbound' ? 'Tú: ' : '') + (l.body || kinds[l.type === 'sticker' ? 'sticker' : l.media_type] || '📎 Adjunto');
            },
            mediaKind: function (m) {
                if (!m.media_url) return '';
                if (m.type === 'sticker') return 'sticker';
                var t = String(m.media_type || m.type || '').toLowerCase(), ext = (m.media_url.split('?')[0].split('.').pop() || '').toLowerCase();
                if (t.indexOf('image') === 0 || t === 'sticker' || /^(jpe?g|png|gif|webp|avif)$/.test(ext)) return 'image';
                if (t.indexOf('video') === 0 || /^(mp4|webm|mov)$/.test(ext)) return 'video';
                if (t.indexOf('audio') === 0 || t === 'ptt' || /^(mp3|ogg|oga|opus|m4a|wav)$/.test(ext)) return 'audio';
                return 'file';
            },
            // Formato estilo WhatsApp (*negrita*, _cursiva_, ~tachado~, `código`) y enlaces con _blank. Escapa todo antes: el resultado es seguro para x-html.
            rich: function (text) {
                if (!text) return '';
                var esc = function (t) { return t.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); };
                var fmt = function (t) {
                    t = esc(t);
                    var code = [];
                    t = t.replace(/```([\s\S]+?)```/g, function (_, c) { code.push('<pre>' + c.replace(/^\n|\n$/g, '') + '</pre>'); return '\u0000' + (code.length - 1) + '\u0000'; });
                    t = t.replace(/`([^`\n]+)`/g, function (_, c) { code.push('<code>' + c + '</code>'); return '\u0000' + (code.length - 1) + '\u0000'; });
                    [['\\*', 'b'], ['_', 'i'], ['~', 's']].forEach(function (p) {
                        // Admite anidar formatos (_*texto*_): el marcador puede ir pegado a otro marcador.
                        t = t.replace(new RegExp('(^|[\\s(¿¡_*~])' + p[0] + '([^\\s' + p[0] + '](?:[^' + p[0] + '\\n]*[^\\s' + p[0] + '])?)' + p[0] + '(?=$|[\\s).,;:!?_*~])', 'g'), '$1<' + p[1] + '>$2</' + p[1] + '>');
                    });
                    return t.replace(/\u0000(\d+)\u0000/g, function (_, i) { return code[+i]; });
                };
                // Los enlaces van aparte para que _ o * dentro de una URL no se lean como formato.
                var out = '', last = 0, re = /(https?:\/\/[^\s<]+|www\.[^\s<]+)/gi, m;
                while ((m = re.exec(text))) {
                    var url = m[0], tail = /[.,;:!?)\]]+$/.exec(url);
                    if (tail) url = url.slice(0, -tail[0].length);
                    out += fmt(text.slice(last, m.index));
                    out += '<a href="' + esc(/^www\./i.test(url) ? 'https://' + url : url) + '" target="_blank" rel="noopener noreferrer nofollow">' + esc(url) + '</a>';
                    last = m.index + url.length; re.lastIndex = last;
                }
                return out + fmt(text.slice(last));
            },
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
            // ---------- Web Push ----------
            async pushInit() {
                if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) { this.pushState = 'unsupported'; return; }
                if (Notification.permission === 'denied') { this.pushState = 'denied'; return; }
                try {
                    var reg = await navigator.serviceWorker.ready, sub = await reg.pushManager.getSubscription();
                    this.pushState = sub && Notification.permission === 'granted' ? 'on' : 'off';
                    if (sub) this.api('/push/subscribe', { method: 'POST', body: sub.toJSON() }).catch(function () {}); // reasigna el dispositivo al agente actual
                } catch (e) { this.pushState = 'off'; }
            },
            async togglePush() {
                try {
                    var reg = await navigator.serviceWorker.ready, sub = await reg.pushManager.getSubscription();
                    if (this.pushState === 'on') {
                        if (sub) { await this.api('/push/unsubscribe', { method: 'POST', body: { endpoint: sub.endpoint } }); await sub.unsubscribe(); }
                        this.pushState = 'off'; return;
                    }
                    if ((await Notification.requestPermission()) !== 'granted') { this.pushState = Notification.permission === 'denied' ? 'denied' : 'off'; return; }
                    var key = (await this.api('/push/key')).public_key;
                    if (!key) { this.notify('Las notificaciones no están configuradas en el servidor.'); return; }
                    var raw = atob(key.replace(/-/g, '+').replace(/_/g, '/')), bytes = new Uint8Array(raw.length);
                    for (var i = 0; i < raw.length; i++) bytes[i] = raw.charCodeAt(i);
                    sub = sub || await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: bytes });
                    await this.api('/push/subscribe', { method: 'POST', body: sub.toJSON() });
                    this.pushState = 'on'; this.notify('Notificaciones activadas');
                } catch (e) { this.notify('No se pudieron activar: ' + (e.message || e)); }
            },

            async testPush() {
                try {
                    if ('caches' in window) await caches.delete('aero-diag');
                    var r = await this.api('/push/test', { method: 'POST' });
                    if (!r.sent) { this.notify('No se envió: ' + r.reason, 9000); return; }
                    this.notify('Enviada a ' + r.devices + ' dispositivo(s). Esperando confirmación…', 6000);
                    var self = this, tries = 0;
                    var check = async function () {
                        var res = 'caches' in window ? await caches.open('aero-diag').then(function (c) { return c.match('/__last_push'); }) : null;
                        var info = res ? await res.json() : null;
                        if (info) { self.notify(info.shown ? 'Este dispositivo recibió y mostró la notificación.' : 'Llegó pero no se pudo mostrar: ' + info.error, 10000); return; }
                        if (++tries < 6) setTimeout(check, 1500);
                        else self.notify('Este dispositivo NO recibió el aviso. Revisa batería, no molestar y ajustes de notificaciones de Android.', 12000);
                    };
                    setTimeout(check, 1500);
                } catch (e) { this.notify('Error: ' + (e.message || e), 9000); }
            },

            async install() { if (!this.installPrompt) return; this.installPrompt.prompt(); await this.installPrompt.userChoice; this.installPrompt = null; this.sheet = ''; },
        };
    };

    function autoGrow(el) { el.style.height = 'auto'; el.style.height = Math.min(el.scrollHeight, 140) + 'px'; }

    // ---------- deslizar con mouse en escritorio (los chips de filtros y modos solo tienen scroll táctil) ----------
    (function () {
        var SEL = '.filters, .modes';

        // rueda vertical del mouse -> scroll horizontal
        document.addEventListener('wheel', function (e) {
            var el = e.target.closest && e.target.closest(SEL);
            if (!el || el.scrollWidth <= el.clientWidth || Math.abs(e.deltaY) <= Math.abs(e.deltaX)) return;
            el.scrollLeft += e.deltaY;
            e.preventDefault();
        }, { passive: false });

        // clic y arrastre con el mouse
        var drag = null;
        document.addEventListener('pointerdown', function (e) {
            if (e.pointerType !== 'mouse') return;
            var el = e.target.closest && e.target.closest(SEL);
            if (!el) return;
            drag = { el: el, x: e.clientX, left: el.scrollLeft, moved: false };
        });
        document.addEventListener('pointermove', function (e) {
            if (!drag) return;
            var dx = e.clientX - drag.x;
            if (Math.abs(dx) > 3) drag.moved = true;
            drag.el.scrollLeft = drag.left - dx;
        });
        document.addEventListener('pointerup', function () {
            if (drag && drag.moved) {
                // evita que el arrastre dispare el clic del chip que quedó debajo del cursor
                var el = drag.el;
                var block = function (e) { e.stopPropagation(); e.preventDefault(); el.removeEventListener('click', block, true); };
                el.addEventListener('click', block, true);
            }
            drag = null;
        });
    })();
})();
