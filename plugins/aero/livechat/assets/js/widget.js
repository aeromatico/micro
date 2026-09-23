(function () {
    'use strict';

    var script = document.currentScript || (function () {
        var scripts = document.getElementsByTagName('script');
        return scripts[scripts.length - 1];
    })();

    var widgetKey = script.getAttribute('data-livechat-key');
    var base = (script.getAttribute('data-livechat-base') || '').replace(/\/$/, '');

    if (!widgetKey || !base) {
        console.warn('aero/livechat: falta data-livechat-key o data-livechat-base en el <script>.');
        return;
    }

    var storageKey = 'aero_livechat_token_' + widgetKey;
    var state = {
        visitorToken: null,
        lastMessageId: 0,
        open: false,
        started: false,
        pollTimer: null,
        unreadTimer: null,
    };

    function api(path, opts) {
        opts = opts || {};
        return fetch(base + '/api/v1/livechat/' + path, {
            method: opts.method || 'GET',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: opts.body ? JSON.stringify(opts.body) : undefined,
        }).then(function (r) { return r.json(); });
    }

    function getToken() {
        try { return localStorage.getItem(storageKey); } catch (e) { return null; }
    }
    function setToken(token) {
        try { localStorage.setItem(storageKey, token); } catch (e) {}
    }

    // ---- UI ----
    var css = ''
        + '#aero-livechat-bubble{position:fixed;bottom:20px;right:20px;width:56px;height:56px;border-radius:50%;'
        + 'background:' + '__COLOR__' + ';box-shadow:0 4px 14px rgba(0,0,0,.25);cursor:pointer;z-index:999999;'
        + 'display:flex;align-items:center;justify-content:center;transition:transform .15s;}'
        + '#aero-livechat-bubble:hover{transform:scale(1.06);}'
        + '#aero-livechat-badge{position:absolute;top:-4px;right:-4px;background:#ef4444;color:#fff;border-radius:10px;'
        + 'font:11px/18px sans-serif;min-width:18px;height:18px;text-align:center;padding:0 4px;display:none;}'
        + '#aero-livechat-panel{position:fixed;bottom:86px;right:20px;width:340px;max-width:92vw;height:460px;max-height:75vh;'
        + 'background:#fff;border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,.25);display:none;flex-direction:column;'
        + 'overflow:hidden;z-index:999999;font-family:-apple-system,Segoe UI,Roboto,sans-serif;}'
        + '#aero-livechat-header{background:' + '__COLOR__' + ';color:#fff;padding:14px 16px;font-weight:600;font-size:14px;}'
        + '#aero-livechat-log{flex:1;overflow-y:auto;padding:12px;background:#f9fafb;display:flex;flex-direction:column;gap:8px;}'
        + '.aero-livechat-msg{max-width:78%;padding:8px 12px;border-radius:12px;font-size:13px;line-height:1.4;word-wrap:break-word;}'
        + '.aero-livechat-msg.agent{align-self:flex-end;background:' + '__COLOR__' + ';color:#fff;}'
        + '.aero-livechat-msg.contact{align-self:flex-start;background:#e5e7eb;color:#111827;}'
        + '.aero-livechat-msg.system{align-self:center;background:transparent;color:#6b7280;font-size:12px;font-style:italic;}'
        + '#aero-livechat-form{display:flex;border-top:1px solid #e5e7eb;padding:8px;gap:6px;}'
        + '#aero-livechat-input{flex:1;border:1px solid #d1d5db;border-radius:8px;padding:8px 10px;font-size:13px;resize:none;color:#111827;background:#fff;}'
        + '#aero-livechat-send{background:' + '__COLOR__' + ';color:#fff;border:none;border-radius:8px;padding:0 14px;font-size:13px;cursor:pointer;}'
        + '#aero-livechat-close{background:transparent;border:none;color:#fff;float:right;cursor:pointer;font-size:16px;line-height:1;}'
        + '#aero-livechat-prechat{flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:10px;background:#fff;}'
        + '#aero-livechat-prechat p{margin:0 0 4px;font-size:13px;color:#374151;}'
        + '.aero-livechat-pc-input{border:1px solid #d1d5db;border-radius:8px;padding:9px 11px;font-size:13px;width:100%;'
        + 'box-sizing:border-box;color:#111827;background:#fff;}'
        + '#aero-livechat-pc-error{color:#dc2626;font-size:12px;min-height:14px;}'
        + '#aero-livechat-pc-submit{background:' + '__COLOR__' + ';color:#fff;border:none;border-radius:8px;padding:10px;font-size:13px;cursor:pointer;font-weight:600;}';

    function injectStyle(color) {
        var style = document.createElement('style');
        style.textContent = css.split('__COLOR__').join(color);
        document.head.appendChild(style);
    }

    function el(tag, attrs, html) {
        var e = document.createElement(tag);
        for (var k in attrs) { e.setAttribute(k, attrs[k]); }
        if (html !== undefined) e.innerHTML = html;
        return e;
    }

    function buildUI() {
        var bubble = el('div', { id: 'aero-livechat-bubble' },
            '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-8.5 8.5 8.38 8.38 0 0 1-4-1L3 20l1-5.5A8.38 8.38 0 0 1 3 11.5 8.38 8.38 0 0 1 12.5 3 8.38 8.38 0 0 1 21 11.5z"/></svg>'
            + '<span id="aero-livechat-badge">0</span>');
        document.body.appendChild(bubble);

        var panel = el('div', { id: 'aero-livechat-panel' },
            '<div id="aero-livechat-header"><button id="aero-livechat-close">&times;</button><span id="aero-livechat-title">Chat</span></div>'
            + '<div id="aero-livechat-prechat">'
            + '<p>Antes de empezar, contanos quién sos:</p>'
            + '<input id="aero-livechat-pc-name" class="aero-livechat-pc-input" placeholder="Nombre">'
            + '<input id="aero-livechat-pc-email" class="aero-livechat-pc-input" type="email" placeholder="Correo">'
            + '<input id="aero-livechat-pc-phone" class="aero-livechat-pc-input" type="tel" placeholder="Celular">'
            + '<div id="aero-livechat-pc-error"></div>'
            + '<button id="aero-livechat-pc-submit">Iniciar chat</button>'
            + '</div>'
            + '<div id="aero-livechat-log" style="display:none;"></div>'
            + '<div id="aero-livechat-form" style="display:none;">'
            + '<textarea id="aero-livechat-input" rows="1" placeholder="Escribe un mensaje..."></textarea>'
            + '<button id="aero-livechat-send">Enviar</button>'
            + '</div>');
        document.body.appendChild(panel);

        bubble.addEventListener('click', togglePanel);
        document.getElementById('aero-livechat-close').addEventListener('click', togglePanel);
        document.getElementById('aero-livechat-send').addEventListener('click', sendMessage);
        document.getElementById('aero-livechat-input').addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
        });
        document.getElementById('aero-livechat-pc-submit').addEventListener('click', submitPreChat);
        ['aero-livechat-pc-name', 'aero-livechat-pc-email', 'aero-livechat-pc-phone'].forEach(function (id) {
            document.getElementById(id).addEventListener('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); submitPreChat(); }
            });
        });
    }

    function showPreChat() {
        document.getElementById('aero-livechat-prechat').style.display = 'flex';
        document.getElementById('aero-livechat-log').style.display = 'none';
        document.getElementById('aero-livechat-form').style.display = 'none';
    }

    function showChat() {
        document.getElementById('aero-livechat-prechat').style.display = 'none';
        document.getElementById('aero-livechat-log').style.display = 'flex';
        document.getElementById('aero-livechat-form').style.display = 'flex';
    }

    function togglePanel() {
        state.open = !state.open;
        document.getElementById('aero-livechat-panel').style.display = state.open ? 'flex' : 'none';
        if (!state.open) { stopPolling(); return; }

        hideBadge();

        if (state.started || getToken()) {
            showChat();
            ensureStarted().then(loadMessages);
            startPolling();
        } else {
            showPreChat();
        }
    }

    function submitPreChat() {
        var name = document.getElementById('aero-livechat-pc-name').value.trim();
        var email = document.getElementById('aero-livechat-pc-email').value.trim();
        var phone = document.getElementById('aero-livechat-pc-phone').value.trim();
        var errorEl = document.getElementById('aero-livechat-pc-error');

        if (!name || !email || !phone) {
            errorEl.textContent = 'Completá los tres campos para empezar.';
            return;
        }
        if (!/^\S+@\S+\.\S+$/.test(email)) {
            errorEl.textContent = 'Ese correo no parece válido.';
            return;
        }
        errorEl.textContent = '';

        var submitBtn = document.getElementById('aero-livechat-pc-submit');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Iniciando...';

        state.started = true;
        api('start', {
            method: 'POST',
            body: { widget_key: widgetKey, visitor_token: getToken(), name: name, email: email, phone: phone, page_url: location.href },
        }).then(function (res) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Iniciar chat';

            if (res.error) {
                state.started = false;
                errorEl.textContent = res.message || 'No se pudo iniciar el chat. Probá de nuevo.';
                return;
            }

            applyStartResult(res);
            showChat();
            startPolling();
        });
    }

    function hideBadge() {
        var badge = document.getElementById('aero-livechat-badge');
        badge.style.display = 'none';
        badge.textContent = '0';
    }

    function renderMessage(m) {
        var log = document.getElementById('aero-livechat-log');
        var div = el('div', { class: 'aero-livechat-msg ' + m.from }, escapeHtml(m.body));
        log.appendChild(div);
        log.scrollTop = log.scrollHeight;
    }

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML.replace(/\n/g, '<br>');
    }

    function applyStartResult(res) {
        state.visitorToken = res.visitor_token;
        setToken(res.visitor_token);
        if (res.inbox) {
            document.getElementById('aero-livechat-title').textContent = res.inbox.name || 'Chat';
        }
        document.getElementById('aero-livechat-log').innerHTML = '';
        (res.messages || []).forEach(function (m) {
            renderMessage(m);
            state.lastMessageId = Math.max(state.lastMessageId, m.id);
        });
    }

    /** Solo para visitantes QUE YA tienen token (volvieron) — los nuevos pasan por submitPreChat(). */
    function ensureStarted() {
        if (state.visitorToken) { return Promise.resolve(); }

        return api('start', {
            method: 'POST',
            body: { widget_key: widgetKey, visitor_token: getToken(), page_url: location.href },
        }).then(function (res) {
            if (res.error) { return; }
            applyStartResult(res);
        });
    }

    function loadMessages() {
        if (!state.visitorToken) return;
        api('messages?widget_key=' + widgetKey + '&visitor_token=' + state.visitorToken + '&after_id=' + state.lastMessageId)
            .then(function (res) {
                (res.messages || []).forEach(function (m) {
                    renderMessage(m);
                    state.lastMessageId = Math.max(state.lastMessageId, m.id);
                });
            });
    }

    function sendMessage() {
        var input = document.getElementById('aero-livechat-input');
        var body = input.value.trim();
        if (!body || !state.visitorToken) return;
        input.value = '';

        renderMessage({ from: 'contact', body: body });
        api('message', {
            method: 'POST',
            body: { widget_key: widgetKey, visitor_token: state.visitorToken, body: body },
        }).then(function (res) {
            (res.messages || []).forEach(function (m) {
                state.lastMessageId = Math.max(state.lastMessageId, m.id);
            });
        });
    }

    function startPolling() {
        stopPolling();
        state.pollTimer = setInterval(loadMessages, 4000);
    }
    function stopPolling() {
        if (state.pollTimer) { clearInterval(state.pollTimer); state.pollTimer = null; }
    }

    function pollUnread() {
        var token = getToken();
        if (!token || state.open) return;
        api('unread?widget_key=' + widgetKey + '&visitor_token=' + token).then(function (res) {
            var badge = document.getElementById('aero-livechat-badge');
            if (res.unread > 0) {
                badge.textContent = res.unread;
                badge.style.display = 'block';
            } else {
                hideBadge();
            }
        });
    }

    function init() {
        injectStyle('#4f46e5');
        buildUI();
        state.visitorToken = getToken();
        state.unreadTimer = setInterval(pollUnread, 15000);
        pollUnread();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
