/*
 * Soporte rápido (Aero.Community). Botón flotante en todo el panel: abre un
 * popup para reportar un error, pedir ayuda o sugerir una mejora justo desde
 * la pantalla donde estás, con la URL, el área y (opcional) una captura.
 * API: window.AeroCommunity.open({ area: '...', type: 'bug' })
 * Marcado opcional: <button data-community-report data-community-area="Pedidos">
 */
(function () {
    'use strict';
    var script = document.currentScript;
    if (!script || window.AeroCommunity) { return; }
    var cfg = {
        endpoint: script.getAttribute('data-endpoint'),
        posts: script.getAttribute('data-posts'),
        html2canvas: script.getAttribute('data-html2canvas')
    };

    var errors = [];
    window.addEventListener('error', function (e) {
        errors.push((e.message || 'Error') + (e.filename ? ' @ ' + e.filename.split('/').pop() + ':' + e.lineno : ''));
        if (errors.length > 5) { errors.shift(); }
    });
    window.addEventListener('unhandledrejection', function (e) {
        errors.push('Promesa rechazada: ' + String(e.reason && e.reason.message || e.reason || '').slice(0, 200));
        if (errors.length > 5) { errors.shift(); }
    });

    var el = {}, picked = null, shot = null, state = { type: 'help', area: '' };

    function h(tag, attrs, children) {
        var n = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (k) {
            if (k === 'text') { n.textContent = attrs[k]; } else { n.setAttribute(k, attrs[k]); }
        });
        (children || []).forEach(function (c) { n.appendChild(c); });
        return n;
    }

    function guessArea() {
        var crumb = document.querySelector('.control-breadcrumb, .breadcrumb, [data-control="breadcrumb"]');
        var parts = [];
        if (crumb) { parts = crumb.textContent.split(/\n|›|\//).map(function (s) { return s.trim(); }).filter(Boolean); }
        var title = (document.title || '').split('|')[0].trim();
        return (parts.length ? parts.slice(-2).join(' › ') : title) || location.pathname;
    }

    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        if (m) { return m.content; }
        var i = document.querySelector('input[name="_token"]');
        return i ? i.value : '';
    }

    function loadHtml2canvas() {
        if (window.html2canvas) { return Promise.resolve(window.html2canvas); }
        return new Promise(function (resolve, reject) {
            var s = document.createElement('script');
            s.src = cfg.html2canvas;
            s.onload = function () { resolve(window.html2canvas); };
            s.onerror = reject;
            document.head.appendChild(s);
        });
    }

    function capture() {
        return loadHtml2canvas().then(function (html2canvas) {
            var hidden = [el.fab, el.overlay].filter(Boolean);
            hidden.forEach(function (n) { n.classList.add('aco-hidden'); });
            var outline = picked && picked.element;
            var prev = outline ? outline.style.outline : null;
            if (outline) { outline.style.outline = '3px solid #ef4444'; }
            return html2canvas(document.body, {
                x: window.scrollX, y: window.scrollY,
                width: window.innerWidth, height: window.innerHeight,
                windowWidth: document.documentElement.clientWidth,
                scale: Math.min(window.devicePixelRatio || 1, 1.5),
                logging: false, useCORS: true
            }).then(function (canvas) {
                return canvas.toDataURL('image/png');
            }).finally(function () {
                hidden.forEach(function (n) { n.classList.remove('aco-hidden'); });
                if (outline) { outline.style.outline = prev; }
            });
        });
    }

    function describe(node) {
        var label = node.getAttribute('aria-label') || node.getAttribute('name') || node.id || '';
        var text = (node.innerText || '').trim().slice(0, 60);
        return node.tagName.toLowerCase() + (label ? '[' + label + ']' : '') + (text ? ' «' + text + '»' : '');
    }

    function startPick() {
        el.overlay.classList.add('aco-hidden');
        var hl = h('div', { 'class': 'aco-pick-hl' });
        var tip = h('div', { 'class': 'aco-pick-tip', text: 'Haz clic en la parte con el problema · Esc para cancelar' });
        document.body.appendChild(hl);
        document.body.appendChild(tip);
        var current = null;

        function move(e) {
            var t = e.target;
            if (t.closest('.aco-pick-tip, .aco-fab')) { return; }
            current = t;
            var r = t.getBoundingClientRect();
            hl.style.cssText = 'left:' + r.left + 'px;top:' + r.top + 'px;width:' + r.width + 'px;height:' + r.height + 'px';
        }
        function done(chosen) {
            document.removeEventListener('mousemove', move, true);
            document.removeEventListener('click', click, true);
            document.removeEventListener('keydown', key, true);
            hl.remove(); tip.remove();
            el.overlay.classList.remove('aco-hidden');
            if (chosen) {
                picked = { element: chosen, text: describe(chosen) };
                el.picked.textContent = 'Señalado: ' + picked.text;
            }
        }
        function click(e) { e.preventDefault(); e.stopPropagation(); done(current || e.target); }
        function key(e) { if (e.key === 'Escape') { done(null); } }
        document.addEventListener('mousemove', move, true);
        document.addEventListener('click', click, true);
        document.addEventListener('keydown', key, true);
    }

    function close() {
        if (el.overlay) { el.overlay.remove(); el.overlay = null; }
        picked = null; shot = null;
    }

    function startChat(chat) {
        close();
        document.body.classList.add('aco-chat-on');
        function go() { if (window.AeroLivechat) { window.AeroLivechat.open(chat.identity); } }
        if (window.AeroLivechat) { go(); return; }
        var tag = document.createElement('script');
        tag.src = chat.script;
        tag.async = true;
        tag.setAttribute('data-livechat-key', chat.key);
        tag.setAttribute('data-livechat-base', chat.base);
        tag.setAttribute('data-livechat-launcher', 'off');
        tag.onload = go;
        document.head.appendChild(tag);
    }

    function open(opts) {
        opts = opts || {};
        if (el.overlay) { return; }
        state.type = opts.type || 'help';
        state.area = opts.area || guessArea();

        var types = [['help', 'Necesito ayuda'], ['bug', 'Algo falla'], ['idea', 'Mejora']];
        var typeBox = h('div', { 'class': 'aco-types' }, types.map(function (t) {
            var input = h('input', { type: 'radio', name: 'aco-type', value: t[0] });
            if (t[0] === state.type) { input.checked = true; }
            return h('label', {}, [input, h('span', { text: t[1] })]);
        }));

        var msg = h('textarea', { placeholder: '¿Qué pasó o qué necesitas? Cuéntanos con tus palabras.', maxlength: '4000' });
        var area = h('input', { type: 'text', value: state.area, maxlength: '150' });
        var shotChk = h('input', { type: 'checkbox' }); shotChk.checked = true;
        var shareChk = h('input', { type: 'checkbox' });
        var shareRow = h('label', { 'class': 'aco-row aco-hidden' }, [shareChk, h('span', { text: 'Compartir esta sugerencia con la comunidad' })]);
        var pickBtn = h('button', { type: 'button', 'class': 'aco-btn', text: 'Señalar la parte con el problema' });
        el.picked = h('div', { 'class': 'aco-meta' });
        var err = h('div', { 'class': 'aco-error' });
        var send = h('button', { type: 'button', 'class': 'aco-btn primary', text: 'Abrir ticket' });
        var cancel = h('button', { type: 'button', 'class': 'aco-btn', text: 'Cancelar' });

        typeBox.addEventListener('change', function (e) {
            state.type = e.target.value;
            shareRow.classList.toggle('aco-hidden', state.type !== 'idea');
        });
        shareRow.classList.toggle('aco-hidden', state.type !== 'idea');
        pickBtn.addEventListener('click', startPick);
        cancel.addEventListener('click', close);

        var promise = h('div', { 'class': 'aco-promise' }, [
            h('strong', { text: 'Te respondemos en pocos minutos.' }),
            h('span', { text: ' Cuéntanos qué pasa y abre un ticket: llega con la pantalla, la dirección y los datos que necesitamos.' })
        ]);
        var body = h('div', { 'class': 'aco-body' }, [
            promise, typeBox, msg,
            h('div', {}, [h('div', { 'class': 'aco-meta', text: 'Área' }), area]),
            pickBtn, el.picked,
            h('label', { 'class': 'aco-row' }, [shotChk, h('span', { text: 'Incluir captura de esta pantalla' })]),
            shareRow,
            h('div', { 'class': 'aco-meta', text: 'Enviaremos también la dirección de esta página: ' + location.pathname }),
            err
        ]);
        var head = h('header', {}, [h('strong', { text: 'Soporte' }), (function () {
            var x = h('button', { type: 'button', 'class': 'aco-x', 'aria-label': 'Cerrar', text: '×' });
            x.addEventListener('click', close); return x;
        })()]);
        var modal = h('div', { 'class': 'aco-modal', role: 'dialog', 'aria-modal': 'true' }, [
            head, body, h('div', { 'class': 'aco-actions' }, [cancel, send])
        ]);
        el.overlay = h('div', { 'class': 'aco-overlay' }, [modal]);
        el.overlay.addEventListener('mousedown', function (e) { if (e.target === el.overlay) { close(); } });
        document.body.appendChild(el.overlay);
        msg.focus();

        send.addEventListener('click', function () {
            err.textContent = '';
            if (msg.value.trim().length < 5) { err.textContent = 'Cuéntanos un poco más.'; return; }
            send.disabled = true; send.textContent = 'Abriendo ticket…';

            var getShot = shotChk.checked ? capture().catch(function () { return null; }) : Promise.resolve(null);
            getShot.then(function (dataUri) {
                var fd = new FormData();
                fd.append('type', state.type);
                fd.append('message', msg.value.trim());
                fd.append('area', area.value.trim());
                fd.append('url', location.href);
                fd.append('share', shareChk.checked ? '1' : '');
                if (dataUri) { fd.append('shot', dataUri); }
                fd.append('context', JSON.stringify({
                    title: document.title,
                    userAgent: navigator.userAgent,
                    viewport: window.innerWidth + 'x' + window.innerHeight,
                    language: navigator.language,
                    element: picked ? picked.text : '',
                    errors: errors.slice()
                }));
                return fetch(cfg.endpoint, {
                    method: 'POST', body: fd, credentials: 'same-origin',
                    headers: {
                        'X-AJAX-HANDLER': 'onReport',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf(),
                        'Accept': 'application/json'
                    }
                });
            }).then(function (res) {
                return res.json().catch(function () { return {}; }).then(function (json) {
                    // October 4 (Larajax): el estado va en `__ajax` y los datos al nivel superior.
                    var meta = json.__ajax || {};
                    if (!res.ok || meta.ok === false || !json.id) {
                        throw new Error(meta.message || 'No pudimos enviar el reporte.');
                    }
                    return json;
                });
            }).then(function (json) {
                var ok = h('div', { 'class': 'aco-ok' }, [
                    h('strong', { text: '¡Gracias! Ticket #' + json.ticket + ' abierto' }),
                    h('p', { text: 'Ya lo tenemos. Te respondemos en pocos minutos; lo verás en «Mis reportes».' })
                ]);
                var row = h('div', { 'class': 'aco-ok-actions' });
                // El chat solo se ofrece aquí, al agradecer; quien reporta ya está registrado (sin invitados).
                if (json.chat) {
                    var chatBtn = h('button', { type: 'button', 'class': 'aco-btn primary aco-chat-btn' });
                    chatBtn.innerHTML = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> Chatear ahora';
                    chatBtn.addEventListener('click', function () { startChat(json.chat); });
                    row.appendChild(chatBtn);
                }
                var go = h('a', { href: json.url, 'class': 'aco-btn', text: 'Ver mi ticket' });
                go.style.display = 'inline-block'; go.style.textDecoration = 'none';
                row.appendChild(go);
                ok.appendChild(row);
                modal.replaceChildren(head, ok);
            }).catch(function (e) {
                err.textContent = e.message || 'No pudimos enviar el reporte.';
                send.disabled = false; send.textContent = 'Abrir ticket';
            });
        });
    }

    function mountFab() {
        el.fab = h('button', { type: 'button', 'class': 'aco-fab', 'aria-label': 'Soporte rápido' }, []);
        el.fab.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><span>Ayuda</span>';
        el.fab.addEventListener('click', function () { open(); });
        document.body.appendChild(el.fab);
    }

    document.addEventListener('click', function (e) {
        var t = e.target.closest && e.target.closest('[data-community-report]');
        if (t) {
            e.preventDefault();
            open({ area: t.getAttribute('data-community-area') || undefined, type: t.getAttribute('data-community-type') || undefined });
        }
    });

    window.AeroCommunity = { open: open };
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', mountFab); } else { mountFab(); }
})();
