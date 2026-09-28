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
    var contactStorageKey = 'aero_livechat_contact_' + widgetKey;
    // sessionStorage (no localStorage): "abierto" es un estado de esta
    // pestaña, no algo que deba sobrevivir a cerrar el navegador.
    var openStorageKey = 'aero_livechat_open_' + widgetKey;
    var state = {
        visitorToken: null,
        lastMessageId: 0,
        open: false,
        started: false,
        themeColorDetected: false,
        pollTimer: null,
        unreadTimer: null,
    };

    /**
     * Nunca rechaza la promesa — siempre resuelve con un objeto (con
     * `.error` si algo salió mal). Sin esto, una respuesta que no es JSON
     * (ej. la página HTML de error 429 de "demasiadas peticiones", o un
     * corte de red) hacía que el `.then()` de quien llamó nunca se
     * ejecutara, dejando el botón trabado en "Enviando..." para siempre.
     */
    function api(path, opts) {
        opts = opts || {};
        return fetch(base + '/api/v1/livechat/' + path, {
            method: opts.method || 'GET',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: opts.body ? JSON.stringify(opts.body) : undefined,
        }).then(function (r) {
            return r.json().catch(function () {
                return {
                    error: 'server_error',
                    message: r.status === 429
                        ? 'Demasiados intentos. Esperá un momento y probá de nuevo.'
                        : 'El servidor no respondió correctamente. Probá de nuevo.',
                };
            });
        }).catch(function () {
            return { error: 'network_error', message: 'No se pudo conectar. Revisá tu conexión.' };
        });
    }

    function getToken() {
        try { return localStorage.getItem(storageKey); } catch (e) { return null; }
    }
    function setToken(token) {
        try { localStorage.setItem(storageKey, token); } catch (e) {}
    }

    /**
     * Nombre/correo/celular del pre-chat, recordados en este navegador (no
     * atados a la conversación) para no volver a pedirlos en el mismo
     * equipo — a diferencia del token de sesión, esto sobrevive a
     * "Finalizar" y al cierre automático por inactividad.
     */
    function saveContactInfo(name, email, phone) {
        try { localStorage.setItem(contactStorageKey, JSON.stringify({ name: name, email: email, phone: phone })); } catch (e) {}
    }
    function getContactInfo() {
        try { return JSON.parse(localStorage.getItem(contactStorageKey)) || null; } catch (e) { return null; }
    }
    function prefillContactForm() {
        var info = getContactInfo();
        if (!info) return;
        var nameEl = document.getElementById('aero-livechat-pc-name');
        var emailEl = document.getElementById('aero-livechat-pc-email');
        var phoneEl = document.getElementById('aero-livechat-pc-phone');
        if (nameEl && !nameEl.value) { nameEl.value = info.name || ''; }
        if (emailEl && !emailEl.value) { emailEl.value = info.email || ''; }
        if (phoneEl && !phoneEl.value) { phoneEl.value = info.phone || ''; }
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
        + '.aero-livechat-msg code{background:rgba(0,0,0,.08);border-radius:4px;padding:1px 4px;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12px;}'
        + '.aero-livechat-msg pre{background:rgba(0,0,0,.08);border-radius:6px;padding:6px 8px;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12px;white-space:pre-wrap;word-break:break-word;margin:4px 0;}'
        + '.aero-livechat-msg a{color:inherit;text-decoration:underline;}'
        + '#aero-livechat-toolbar{display:flex;align-items:center;gap:10px;padding:6px 10px 0;border-top:1px solid #e5e7eb;}'
        + '#aero-livechat-toolbar button{background:none;border:none;color:#6b7280;font-size:12px;cursor:pointer;padding:4px 0;display:flex;align-items:center;gap:4px;}'
        + '#aero-livechat-toolbar button:hover{color:' + '__COLOR__' + ';}'
        + '#aero-livechat-toolbar-msg{font-size:11px;color:#6b7280;margin-left:auto;}'
        + '#aero-livechat-form{display:flex;padding:8px;gap:6px;}'
        + '#aero-livechat-input{flex:1;border:1px solid #d1d5db;border-radius:8px;padding:8px 10px;font-size:13px;resize:none;color:#111827;background:#fff;}'
        + '#aero-livechat-send{background:' + '__COLOR__' + ';color:#fff;border:none;border-radius:8px;padding:0 14px;font-size:13px;cursor:pointer;}'
        + '#aero-livechat-close{background:transparent;border:none;color:#fff;float:right;cursor:pointer;font-size:16px;line-height:1;}'
        + '.aero-livechat-attachment-img{max-width:100%;border-radius:8px;display:block;}'
        + '.aero-livechat-attachment-file{color:inherit;text-decoration:underline;font-size:13px;}'
        + '.aero-livechat-attachment-audio{display:block;max-width:100%;width:230px;height:32px;}'
        + '.aero-livechat-attachment-video{display:block;max-width:100%;width:220px;border-radius:8px;}'
        + '#aero-livechat-prechat{flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:10px;background:#fff;}'
        + '#aero-livechat-prechat p{margin:0 0 4px;font-size:13px;color:#374151;}'
        + '.aero-livechat-pc-input{border:1px solid #d1d5db;border-radius:8px;padding:9px 11px;font-size:13px;width:100%;'
        + 'box-sizing:border-box;color:#111827;background:#fff;}'
        + '#aero-livechat-pc-error{color:#dc2626;font-size:12px;min-height:14px;}'
        + '#aero-livechat-pc-submit{background:' + '__COLOR__' + ';color:#fff;border:none;border-radius:8px;padding:10px;font-size:13px;cursor:pointer;font-weight:600;}';

    var styleEl = null;
    function injectStyle(color) {
        var text = css.split('__COLOR__').join(color);
        if (styleEl) { styleEl.textContent = text; return; }
        styleEl = document.createElement('style');
        styleEl.textContent = text;
        document.head.appendChild(styleEl);
    }

    /**
     * Toma el color de acento que ya usa el sitio donde se embebe el widget,
     * en vez de un azul fijo — cada theme del proyecto usa su propio nombre
     * de variable (master: --color-accent, microsites: --color-primary,
     * whatsapp: --brand), así que se prueban los más comunes en orden.
     */
    function detectThemeColor() {
        var candidates = ['--color-accent', '--color-primary', '--brand', '--accent-color', '--primary-color', '--brand-color'];
        try {
            var styles = getComputedStyle(document.documentElement);
            for (var i = 0; i < candidates.length; i++) {
                var val = styles.getPropertyValue(candidates[i]).trim();
                if (val) { return val; }
            }
        } catch (e) {}
        return null;
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
            + '<div id="aero-livechat-toolbar" style="display:none;">'
            + '<button id="aero-livechat-attach-btn" type="button" title="Adjuntar un archivo">📎 Adjuntar</button>'
            + '<input type="file" id="aero-livechat-file-input" style="display:none" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.zip">'
            + '<button id="aero-livechat-transcript-btn" type="button" title="Enviar la conversación a tu correo">✉️ Enviar transcripción</button>'
            + '<button id="aero-livechat-end-btn" type="button" title="Finalizar chat">🔒 Finalizar</button>'
            + '<span id="aero-livechat-toolbar-msg"></span>'
            + '</div>'
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
        document.getElementById('aero-livechat-attach-btn').addEventListener('click', function () {
            document.getElementById('aero-livechat-file-input').click();
        });
        document.getElementById('aero-livechat-file-input').addEventListener('change', function () {
            if (this.files[0]) { uploadAttachment(this.files[0]); }
            this.value = '';
        });
        document.getElementById('aero-livechat-transcript-btn').addEventListener('click', sendTranscript);
        document.getElementById('aero-livechat-end-btn').addEventListener('click', endChat);

        prefillContactForm();
    }

    function showPreChat() {
        document.getElementById('aero-livechat-prechat').style.display = 'flex';
        document.getElementById('aero-livechat-log').style.display = 'none';
        document.getElementById('aero-livechat-toolbar').style.display = 'none';
        document.getElementById('aero-livechat-form').style.display = 'none';
    }

    function showChat() {
        document.getElementById('aero-livechat-prechat').style.display = 'none';
        document.getElementById('aero-livechat-log').style.display = 'flex';
        document.getElementById('aero-livechat-toolbar').style.display = 'flex';
        document.getElementById('aero-livechat-form').style.display = 'flex';
    }

    /** Recuerda si el panel quedó abierto/cerrado en esta pestaña, para heredar el estado al navegar a otra página del sitio. */
    function setOpenFlag(open) {
        try {
            if (open) { sessionStorage.setItem(openStorageKey, '1'); }
            else { sessionStorage.removeItem(openStorageKey); }
        } catch (e) {}
    }

    function openPanel() {
        state.open = true;
        document.getElementById('aero-livechat-panel').style.display = 'flex';
        setOpenFlag(true);
        hideBadge();

        if (state.started || getToken()) {
            showChat();
            ensureStarted().then(loadMessages).catch(function () { stopPolling(); });
            startPolling();
        } else {
            showPreChat();
        }
    }

    function closePanel() {
        state.open = false;
        document.getElementById('aero-livechat-panel').style.display = 'none';
        setOpenFlag(false);
        stopPolling();
    }

    function togglePanel() {
        if (state.open) { closePanel(); } else { openPanel(); }
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

            saveContactInfo(name, email, phone);
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

    /**
     * El mismo mensaje puede llegar por dos caminos casi al mismo tiempo —
     * la respuesta de enviar/subir y el poll de cada 4s que ya lo trajo de
     * vuelta — sin este control se pintaba dos veces.
     */
    var renderedIds = {};
    function renderMessage(m) {
        if (m.id) {
            if (renderedIds[m.id]) { return; }
            renderedIds[m.id] = true;
        }

        var log = document.getElementById('aero-livechat-log');
        var div = el('div', { class: 'aero-livechat-msg ' + m.from });

        if (m.attachment) {
            if (m.attachment.image) {
                div.appendChild(el('a', { href: m.attachment.url, target: '_blank', rel: 'noopener' },
                    '<img class="aero-livechat-attachment-img" src="' + m.attachment.url + '" alt="' + escapeHtml(m.attachment.name) + '">'));
            } else if (m.attachment.audio) {
                div.appendChild(el('audio', { class: 'aero-livechat-attachment-audio', controls: 'controls', preload: 'none', src: m.attachment.url }));
            } else if (m.attachment.video) {
                div.appendChild(el('video', { class: 'aero-livechat-attachment-video', controls: 'controls', preload: 'metadata', playsinline: 'playsinline', src: m.attachment.url }));
            } else {
                div.appendChild(el('a', { href: m.attachment.url, target: '_blank', rel: 'noopener', class: 'aero-livechat-attachment-file' },
                    '📎 ' + escapeHtml(m.attachment.name)));
            }
        }
        if (m.body) {
            var bodyDiv = el('div', {}, formatRich(m.body));
            if (m.attachment) { bodyDiv.style.marginTop = '4px'; }
            div.appendChild(bodyDiv);
        }

        log.appendChild(div);
        log.scrollTop = log.scrollHeight;
    }

    /**
     * Mensajes propios pintados al toque (optimistas, sin id todavía) que
     * esperan su confirmación del servidor. La confirmación puede llegar por
     * DOS caminos que corren en paralelo — la respuesta del propio envío, o
     * el poll de cada 4s, lo que responda primero — así que ambos pasan por
     * acá antes de pintar, para no duplicar el que ya se ve.
     */
    var pendingOwn = [];
    function handleIncoming(m) {
        if (m.id && renderedIds[m.id]) { return; }

        if (m.from === 'contact' && !m.attachment && pendingOwn.length) {
            var idx = pendingOwn.indexOf(m.body);
            if (idx !== -1) {
                pendingOwn.splice(idx, 1);
                if (m.id) { renderedIds[m.id] = true; }
                return;
            }
        }

        renderMessage(m);
    }

    function uploadAttachment(file) {
        if (!state.visitorToken) return;
        if (file.size > 8 * 1024 * 1024) {
            setToolbarMsg('El archivo supera los 8 MB.');
            return;
        }

        setToolbarMsg('Subiendo...');

        var formData = new FormData();
        formData.append('widget_key', widgetKey);
        formData.append('visitor_token', state.visitorToken);
        formData.append('file', file);

        fetch(base + '/api/v1/livechat/attachment', { method: 'POST', body: formData })
            .then(function (r) {
                return r.json().catch(function () {
                    return { error: 'server_error', message: r.status === 429 ? 'Demasiados intentos. Esperá un momento.' : 'El servidor no respondió correctamente.' };
                });
            })
            .then(function (res) {
                if (res.error) {
                    setToolbarMsg(res.message || 'No se pudo subir el archivo.');
                    return;
                }
                setToolbarMsg('');
                (res.messages || []).forEach(function (m) {
                    if (m.id > state.lastMessageId) { handleIncoming(m); }
                    state.lastMessageId = Math.max(state.lastMessageId, m.id);
                });
            })
            .catch(function () {
                setToolbarMsg('No se pudo conectar. Probá de nuevo.');
            });
    }

    function sendTranscript() {
        if (!state.visitorToken) return;
        setToolbarMsg('Enviando...');

        api('transcript', {
            method: 'POST',
            body: { widget_key: widgetKey, visitor_token: state.visitorToken },
        }).then(function (res) {
            setToolbarMsg(res.error ? (res.message || 'No se pudo enviar.') : '¡Enviado! Revisá tu correo.');
        });
    }

    function endChat() {
        if (!state.visitorToken) return;
        if (!window.confirm('¿Finalizar esta conversación? Se cierra el chat actual; si volvés a escribir, empieza uno nuevo.')) return;

        api('end', {
            method: 'POST',
            body: { widget_key: widgetKey, visitor_token: state.visitorToken },
        }).then(function () {
            resetLocalSession();
            showPreChat();
        });
    }

    /**
     * Borra la sesión guardada (token local) y limpia la UI — sin llamar a
     * /end, porque la conversación ya está resuelta del lado del servidor
     * (el visitante la cerró, el agente la finalizó desde el panel, o se
     * cerró sola por inactividad). Se usa cada vez que un poll detecta
     * status "resolved", así el widget reacciona sin importar quién cerró.
     */
    function resetLocalSession() {
        try { localStorage.removeItem(storageKey); } catch (e) {}
        state.visitorToken = null;
        state.started = false;
        state.lastMessageId = 0;
        stopPolling();

        document.getElementById('aero-livechat-log').innerHTML = ''; renderedIds = {}; pendingOwn = [];
        document.getElementById('aero-livechat-title').textContent = 'Chat';
        document.getElementById('aero-livechat-pc-error').textContent = '';
        // Los campos NO se borran acá a propósito: nombre/correo/celular
        // quedan recordados en este equipo (ver saveContactInfo) para no
        // volver a pedirlos en el próximo chat.
        prefillContactForm();
    }

    /** El chat se finalizó (desde el panel, o solo por inactividad) mientras el visitante lo tenía abierto/cerrado. */
    function handleResolvedElsewhere() {
        var wasOpen = state.open;
        resetLocalSession();

        if (wasOpen) {
            showPreChat();
            document.getElementById('aero-livechat-pc-error').textContent = 'Esta conversación se cerró. Completá tus datos para iniciar una nueva.';
        }
    }

    function setToolbarMsg(text) {
        var el = document.getElementById('aero-livechat-toolbar-msg');
        if (el) { el.textContent = text; }
    }

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML.replace(/\n/g, '<br>');
    }

    /**
     * Mismo formato que el composer del PWA de WhatsApp (tema whatsapp,
     * assets/js/app.js::rich): *negrita*, _cursiva_, ~tachado~, `código`,
     * ```bloques```, y enlaces con _blank. El agente escribe igual en los dos
     * canales y el visitante del sitio ve el mismo resultado. Escapa todo
     * antes de aplicar el formato — el resultado es seguro para innerHTML.
     */
    function formatRich(text) {
        if (!text) return '';
        var esc = function (t) { return t.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); };
        var fmt = function (t) {
            t = esc(t);
            var code = [];
            t = t.replace(/```([\s\S]+?)```/g, function (_, c) { code.push('<pre>' + c.replace(/^\n|\n$/g, '') + '</pre>'); return '\u0000' + (code.length - 1) + '\u0000'; });
            t = t.replace(/`([^`\n]+)`/g, function (_, c) { code.push('<code>' + c + '</code>'); return '\u0000' + (code.length - 1) + '\u0000'; });
            [['\\*', 'b'], ['_', 'i'], ['~', 's']].forEach(function (p) {
                t = t.replace(new RegExp('(^|[\\s(¿¡])' + p[0] + '([^\\s' + p[0] + '](?:[^' + p[0] + '\\n]*[^\\s' + p[0] + '])?)' + p[0] + '(?=$|[\\s).,;:!?])', 'g'), '$1<' + p[1] + '>$2</' + p[1] + '>');
            });
            return t.replace(/\u0000(\d+)\u0000/g, function (_, i) { return code[+i]; });
        };
        var out = '', last = 0, re = /(https?:\/\/[^\s<]+|www\.[^\s<]+)/gi, m;
        while ((m = re.exec(text))) {
            var url = m[0], tail = /[.,;:!?)\]]+$/.exec(url);
            if (tail) url = url.slice(0, -tail[0].length);
            out += fmt(text.slice(last, m.index));
            out += '<a href="' + esc(/^www\./i.test(url) ? 'https://' + url : url) + '" target="_blank" rel="noopener noreferrer nofollow">' + esc(url) + '</a>';
            last = m.index + url.length; re.lastIndex = last;
        }
        return (out + fmt(text.slice(last))).replace(/\n/g, '<br>');
    }

    function applyStartResult(res) {
        state.visitorToken = res.visitor_token;
        setToken(res.visitor_token);
        if (res.inbox) {
            document.getElementById('aero-livechat-title').textContent = res.inbox.name || 'Chat';
            // El color del theme del sitio manda; el color configurado en el
            // inbox queda como respaldo solo si no se detectó ninguno acá.
            if (!state.themeColorDetected && res.inbox.color) {
                injectStyle(res.inbox.color);
            }
        }
        document.getElementById('aero-livechat-log').innerHTML = ''; renderedIds = {}; pendingOwn = [];
        (res.messages || []).forEach(function (m) {
            renderMessage(m);
            state.lastMessageId = Math.max(state.lastMessageId, m.id);
        });
    }

    /** Solo para visitantes QUE YA tienen token (volvieron) — los nuevos pasan por submitPreChat(). */
    function ensureStarted() {
        if (state.visitorToken) { return Promise.resolve(); }

        var savedToken = getToken();
        if (!savedToken) {
            // No hay token ni local ni recién obtenido: pedile los datos, no
            // sigas al chat vacío. (togglePanel ya debería haber mostrado el
            // pre-chat en este caso, esto es un respaldo.)
            showPreChat();
            return Promise.reject(new Error('no_token'));
        }

        return api('start', {
            method: 'POST',
            body: { widget_key: widgetKey, visitor_token: savedToken, page_url: location.href },
        }).then(function (res) {
            if (res.error) {
                // El token guardado ya no existe del lado del servidor (ej. se
                // limpió la base para pruebas): arrancar de cero pidiendo los
                // datos de nuevo, en vez de dejar el chat en blanco.
                resetLocalSession();
                showPreChat();
                return Promise.reject(res);
            }
            applyStartResult(res);
        });
    }

    function loadMessages() {
        if (!state.visitorToken) return;
        api('messages?widget_key=' + widgetKey + '&visitor_token=' + state.visitorToken + '&after_id=' + state.lastMessageId)
            .then(function (res) {
                (res.messages || []).forEach(function (m) {
                    handleIncoming(m);
                    state.lastMessageId = Math.max(state.lastMessageId, m.id);
                });
                // Se cerró desde el otro lado (panel) o por inactividad —
                // reacciona igual que si el visitante hubiera tocado "Finalizar".
                if (res.status === 'resolved') { handleResolvedElsewhere(); }
            });
    }

    function sendMessage() {
        var input = document.getElementById('aero-livechat-input');
        var body = input.value.trim();
        if (!body || !state.visitorToken) return;
        input.value = '';

        // Optimista: se pinta al toque, sin esperar la ida y vuelta al servidor.
        renderMessage({ from: 'contact', body: body });
        pendingOwn.push(body);

        api('message', {
            method: 'POST',
            body: { widget_key: widgetKey, visitor_token: state.visitorToken, body: body },
        }).then(function (res) {
            (res.messages || []).forEach(function (m) {
                if (m.id > state.lastMessageId) { handleIncoming(m); }
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
            if (res.status === 'resolved') { handleResolvedElsewhere(); return; }

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
        var themeColor = detectThemeColor();
        state.themeColorDetected = !!themeColor;
        injectStyle(themeColor || '#4f46e5');
        buildUI();
        // OJO: no copiar getToken() a state.visitorToken acá — recién se
        // valida contra el servidor en ensureStarted()/submitPreChat(). Si
        // el token local quedó huérfano (ej. se limpió la base de prueba),
        // esto evitaba que ensureStarted() detectara el error y mostrara el
        // pre-chat de nuevo.
        state.unreadTimer = setInterval(pollUnread, 15000);
        pollUnread();

        // El visitante tenía el panel abierto (o solo minimizado/cerrado) al
        // cambiar de página — hereda ese mismo estado en la nueva, en vez de
        // arrancar siempre cerrado.
        try {
            if (sessionStorage.getItem(openStorageKey) === '1') { openPanel(); }
        } catch (e) {}
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
