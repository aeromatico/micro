/*
 * Ayuda guiada (Aero.Help). Cajón lateral de conversación con flujos paso a
 * paso, búsqueda en la documentación, guías, videos y escalado a soporte.
 * API: window.AeroHelp.open({ area: '...' })
 * Si Aero.Community ya puso su botón flotante, ese botón abre este panel
 * (ver quick-support.js); si no, se monta el botón propio.
 * Todo texto del servidor se escapa; solo se aceptan enlaces http(s) o relativos.
 */
(function () {
    'use strict';
    var script = document.currentScript;
    if (!script || window.AeroHelp) { return; }
    var endpoint = script.getAttribute('data-endpoint');

    var el = {}, session = null, busy = false, started = false;

    var ICON = {
        help: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>',
        close: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>',
        plus: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>',
        bug: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>',
        ticket: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9a2 2 0 0 0 0 6v3h18v-3a2 2 0 0 1 0-6V6H3z"/><path d="M13 6v12" stroke-dasharray="2 2"/></svg>',
        send: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5M5 12l7-7 7 7"/></svg>'
    };

    function h(tag, attrs, children) {
        var n = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (k) {
            if (k === 'text') { n.textContent = attrs[k]; } else if (k === 'html') { n.innerHTML = attrs[k]; } else { n.setAttribute(k, attrs[k]); }
        });
        (children || []).forEach(function (c) { if (c) { n.appendChild(c); } });
        return n;
    }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function safeUrl(u) {
        u = String(u || '');
        return (/^https?:\/\//i.test(u) || /^\/(?!\/)/.test(u)) ? u : '#';
    }

    /** Markdown mínimo y seguro: **negrita**, *cursiva*, `código`, [texto](url), listas con "- " y saltos de línea. */
    function md(text) {
        var lines = esc(text).split('\n'), out = [], list = false;
        lines.forEach(function (line) {
            var li = /^\s*-\s+(.*)$/.exec(line);
            if (li && !list) { out.push('<ul>'); list = true; }
            if (!li && list) { out.push('</ul>'); list = false; }
            out.push(li ? '<li>' + li[1] + '</li>' : (line.trim() === '' ? '' : '<p>' + line + '</p>'));
        });
        if (list) { out.push('</ul>'); }
        return out.join('')
            .replace(/`([^`]+)`/g, '<code>$1</code>')
            .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
            .replace(/(^|[^*])\*([^*\s][^*]*)\*/g, '$1<em>$2</em>')
            .replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, function (m, t, u) {
                var url = safeUrl(u.replace(/&amp;/g, '&'));
                return url === '#' ? t : '<a href="' + esc(url) + '" target="_blank" rel="noopener">' + t + '</a>';
            });
    }

    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        if (m) { return m.content; }
        var i = document.querySelector('input[name="_token"]');
        return i ? i.value : '';
    }

    function call(handler, data) {
        var fd = new FormData();
        Object.keys(data || {}).forEach(function (k) { fd.append(k, data[k]); });
        return fetch(endpoint, {
            method: 'POST', body: fd, credentials: 'same-origin',
            headers: { 'X-AJAX-HANDLER': handler, 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' }
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (json) {
                var meta = json.__ajax || {};
                if (!res.ok || meta.ok === false) { throw new Error(meta.message || 'No pudimos completar la acción.'); }
                return json;
            });
        });
    }

    function guessArea() {
        var crumb = document.querySelector('.control-breadcrumb, .breadcrumb, [data-control="breadcrumb"]');
        var parts = crumb ? crumb.textContent.split(/\n|›|\//).map(function (s) { return s.trim(); }).filter(Boolean) : [];
        var title = (document.title || '').split('|')[0].trim();
        return (parts.length ? parts.slice(-2).join(' › ') : title) || location.pathname;
    }

    /* ---------- pintado ---------- */

    function scroll() { el.feed.scrollTop = el.feed.scrollHeight; }

    function userMsg(text) {
        el.feed.appendChild(h('div', { 'class': 'ahp-msg u', text: text }));
        scroll();
    }

    function error(msg) {
        el.feed.appendChild(h('div', { 'class': 'ahp-err', role: 'alert', text: msg }));
        scroll();
    }

    function dots(on) {
        if (on) { el.dots = h('div', { 'class': 'ahp-dots', 'aria-label': 'Escribiendo' }, [h('i'), h('i'), h('i')]); el.feed.appendChild(el.dots); scroll(); }
        else if (el.dots) { el.dots.remove(); el.dots = null; }
    }

    function card(kind, title, summary, actions, media) {
        var body = h('div', { 'class': 'ahp-card-b' }, [
            h('div', { 'class': 'ahp-card-k', text: kind }),
            h('div', { 'class': 'ahp-card-t', text: title }),
            summary ? h('div', { 'class': 'ahp-card-s', text: summary }) : null
        ]);
        return h('div', { 'class': 'ahp-card' }, [media, body, actions && actions.length ? h('div', { 'class': 'ahp-card-a' }, actions) : null]);
    }

    function linkBtn(label, url, primary) {
        return h('a', { 'class': 'ahp-btn' + (primary ? ' primary' : ''), href: safeUrl(url), target: '_blank', rel: 'noopener', text: label });
    }

    var RENDER = {
        text: function (b) { return h('div', { 'class': 'ahp-msg b', html: md(b.text) }); },

        buttons: function (b) {
            var wrap = h('div', { 'class': 'ahp-chips' });
            b.items.forEach(function (it) {
                var chip = h('button', { type: 'button', 'class': 'ahp-chip', text: it.label });
                chip.addEventListener('click', function () {
                    if (busy) { return; }
                    wrap.classList.add('done'); chip.classList.add('pick');
                    send('choose', it.value, it.label);
                });
                wrap.appendChild(chip);
            });
            return wrap;
        },

        step: function (b) {
            return h('div', { 'class': 'ahp-step' }, [
                h('div', { html: '<b>Paso ' + b.n + ' de ' + b.of + '</b>' + (b.title ? ' · ' + esc(b.title) : '') }),
                h('div', { 'class': 'ahp-bar', role: 'progressbar', 'aria-valuemin': '0', 'aria-valuemax': String(b.of), 'aria-valuenow': String(b.n) }, [h('i', { style: 'width:' + Math.round(b.n / b.of * 100) + '%' })])
            ]);
        },

        doc: function (b) {
            return card('Documentación' + (b.minutes ? ' · ' + b.minutes + ' min' : ''), b.title, b.summary, [linkBtn('Leer el artículo', b.url, true)]);
        },

        guide: function (b) {
            var holder = h('div');
            var inline = h('button', { type: 'button', 'class': 'ahp-btn primary', text: 'Practicar aquí' });
            var shown = false;
            inline.addEventListener('click', function () {
                shown = !shown;
                holder.replaceChildren();
                if (shown) { holder.appendChild(h('iframe', { 'class': 'guide', src: safeUrl(b.embed), sandbox: 'allow-scripts', title: b.title, loading: 'lazy' })); }
                inline.textContent = shown ? 'Ocultar la guía' : 'Practicar aquí';
                scroll();
            });
            var c = card('Guía interactiva', b.title, b.summary, [inline, linkBtn('Abrir en una pestaña', b.url)]);
            c.insertBefore(holder, c.firstChild);
            return c;
        },

        video: function (b) {
            var media = b.provider === 'youtube'
                ? h('iframe', { 'class': 'vid', src: safeUrl(b.src), title: b.title, allow: 'accelerometer; encrypted-media; picture-in-picture', allowfullscreen: '', loading: 'lazy', referrerpolicy: 'strict-origin-when-cross-origin' })
                : h('video', { src: safeUrl(b.src), controls: '', preload: 'metadata', poster: b.poster ? safeUrl(b.poster) : '' });
            return card('Video', b.title, null, b.more ? [linkBtn('Ver el servicio', b.more)] : [], media);
        },

        link: function (b) { return card('Ir a la pantalla', b.label, null, [linkBtn(b.label, b.url, true)]); },

        results: function (b) {
            var wrap = h('div', { 'class': 'ahp-results' });
            b.items.forEach(function (it) {
                var btn = h('button', { type: 'button', 'class': 'ahp-res' }, [h('span', { text: it.label }), it.summary ? h('small', { text: it.summary }) : null]);
                btn.addEventListener('click', function () { if (!busy) { send('choose', it.value, it.label); } });
                wrap.appendChild(btn);
            });
            return wrap;
        },

        escalate: function (b) {
            var note = h('textarea', { placeholder: 'Si quieres, cuéntanos algo más (opcional)', maxlength: '1000', 'aria-label': 'Detalle para el equipo' });
            var msg = h('div', { 'class': 'ahp-err' });
            var row = h('div', { 'class': 'ahp-esc-o' });
            var box = h('div', { 'class': 'ahp-esc' }, [
                h('strong', { text: 'Hablar con una persona' }),
                h('small', { text: 'Enviamos esta conversación y la pantalla donde estás, para que no tengas que repetir nada.' }),
                note, row, msg
            ]);
            b.options.forEach(function (o) {
                var btn = h('button', { type: 'button', 'class': 'ahp-btn primary', text: o.label, title: o.hint });
                btn.addEventListener('click', function () {
                    msg.textContent = '';
                    row.querySelectorAll('button').forEach(function (x) { x.disabled = true; });
                    call('onEscalate', { session: session, via: o.via, note: note.value.trim() }).then(function (res) {
                        box.replaceChildren(h('strong', { text: res.label + ' enviado' }),
                            h('small', { text: 'Ya lo tiene el equipo. Te respondemos en pocos minutos.' }),
                            h('div', { 'class': 'ahp-esc-o' }, [linkBtn('Ver ' + res.label.toLowerCase(), res.url, true), chatBtn(res.chat)]));
                        scroll();
                    }).catch(function (e) {
                        msg.textContent = e.message;
                        row.querySelectorAll('button').forEach(function (x) { x.disabled = false; });
                    });
                });
                row.appendChild(btn);
            });
            return box;
        }
    };

    /** Si hay chat disponible y Community sabe abrirlo, se ofrece seguir conversando con una persona. */
    function chatBtn(chat) {
        if (!chat || !window.AeroCommunity || !window.AeroCommunity.startChat) { return null; }
        var btn = h('button', { type: 'button', 'class': 'ahp-btn', text: 'Chatear ahora' });
        btn.addEventListener('click', function () { close(); window.AeroCommunity.startChat(chat); });
        return btn;
    }

    function render(blocks) {
        // Los bloques del bot entran escalonados para que se sienta una conversación.
        return blocks.reduce(function (chain, b) {
            return chain.then(function () {
                var fn = RENDER[b.type];
                if (fn) { el.feed.appendChild(fn(b)); scroll(); }
                return new Promise(function (r) { setTimeout(r, b.type === 'text' ? 140 : 60); });
            });
        }, Promise.resolve());
    }

    function apply(res) {
        session = res.session;
        if (res.channels) { setChannels(res.channels); }
        el.input.placeholder = res.hint || 'Escribe tu duda…';
        return render(res.blocks || []);
    }

    /* ---------- reporte / ticket rápido (barra superior) ---------- */

    var channels = [];

    function setChannels(list) {
        channels = list || [];
        if (el.quick) { el.quick.style.display = channels.length ? '' : 'none'; }
        if (el.qReport) { el.qReport.style.display = channels.indexOf('report') > -1 ? '' : 'none'; }
        if (el.qTicket) { el.qTicket.style.display = channels.indexOf('ticket') > -1 ? '' : 'none'; }
    }

    function quickForm(via) {
        var isTicket = via === 'ticket';
        var text = h('textarea', { placeholder: isTicket ? 'Describe lo que necesitas…' : 'Cuéntanos qué pasó y qué esperabas que pasara…', maxlength: '1500', 'aria-label': 'Descripción' });
        var kind = h('select', { 'aria-label': 'Tipo' }, [h('option', { value: 'bug', text: 'Algo no funciona' }), h('option', { value: 'help', text: 'Necesito ayuda' })]);
        var prio = h('select', { 'aria-label': 'Prioridad' }, [h('option', { value: 'low', text: 'Baja' }), h('option', { value: 'normal', text: 'Normal', selected: '' }), h('option', { value: 'high', text: 'Alta' }), h('option', { value: 'urgent', text: 'Urgente' })]);
        prio.value = 'normal';
        var msg = h('div', { 'class': 'ahp-err' });
        var go = h('button', { type: 'button', 'class': 'ahp-btn primary', text: isTicket ? 'Abrir ticket' : 'Enviar reporte' });
        var cancel = h('button', { type: 'button', 'class': 'ahp-btn', text: 'Cancelar' });
        var box = h('div', { 'class': 'ahp-qf' }, [
            h('strong', { text: isTicket ? 'Abrir un ticket' : 'Reportar una incidencia' }),
            h('small', { text: 'Incluimos la pantalla donde estás' + (session ? ' y esta conversación' : '') + '.' }),
            isTicket ? h('label', {}, [h('span', { text: 'Prioridad' }), prio]) : h('label', {}, [h('span', { text: 'Tipo' }), kind]),
            text, msg, h('div', { 'class': 'ahp-esc-o' }, [go, cancel])
        ]);
        cancel.addEventListener('click', function () { box.remove(); });
        go.addEventListener('click', function () {
            msg.textContent = '';
            go.disabled = true;
            call('onQuick', { session: session || '', via: via, message: text.value.trim(), kind: kind.value, priority: prio.value, url: location.href, area: guessArea() }).then(function (res) {
                box.replaceChildren(h('strong', { text: res.label + ' enviado' }),
                    h('small', { text: 'Ya lo tiene el equipo. Te respondemos en pocos minutos.' }),
                    h('div', { 'class': 'ahp-esc-o' }, [linkBtn('Ver ' + res.label.toLowerCase(), res.url, true), chatBtn(res.chat)]));
                scroll();
            }).catch(function (e) { msg.textContent = e.message; go.disabled = false; });
        });
        el.feed.appendChild(box);
        scroll();
        text.focus();
    }

    /* ---------- acciones ---------- */

    function setBusy(on) {
        busy = on;
        el.send.disabled = on;
        el.input.disabled = on;
        if (!on) { el.input.focus(); }
    }

    function send(action, value, label) {
        if (busy || !session) { return; }
        setBusy(true);
        userMsg(label || value);
        dots(true);
        call('onSend', { session: session, action: action, value: value }).then(function (res) {
            dots(false);
            return apply(res);
        }).catch(function (e) {
            dots(false);
            error(e.message);
        }).then(function () { setBusy(false); });
    }

    function begin(area) {
        el.feed.replaceChildren();
        session = null;
        setBusy(true);
        dots(true);
        call('onStart', { url: location.href, area: area || guessArea() }).then(function (res) {
            dots(false);
            return apply(res);
        }).catch(function (e) {
            dots(false);
            error(e.message);
        }).then(function () { setBusy(false); });
    }

    function submit() {
        var text = el.input.value.trim();
        if (!text || busy) { return; }
        el.input.value = '';
        grow();
        send('text', text);
    }

    function grow() {
        el.input.style.height = 'auto';
        el.input.style.height = Math.min(el.input.scrollHeight, 120) + 'px';
    }

    function build() {
        el.scrim = h('div', { 'class': 'ahp-scrim' });
        el.feed = h('div', { 'class': 'ahp-feed', 'aria-live': 'polite' });
        el.input = h('textarea', { rows: '1', placeholder: 'Escribe tu duda…', 'aria-label': 'Tu mensaje', maxlength: '500' });
        el.send = h('button', { type: 'button', 'class': 'ahp-send', 'aria-label': 'Enviar', html: ICON.send });

        var fresh = h('button', { type: 'button', 'class': 'ahp-icon', 'aria-label': 'Nueva conversación', title: 'Nueva conversación', html: ICON.plus });
        var x = h('button', { type: 'button', 'class': 'ahp-icon', 'aria-label': 'Cerrar', title: 'Cerrar', html: ICON.close });
        var head = h('div', { 'class': 'ahp-head' }, [h('strong', { html: 'Ayuda<small>Guías, documentación y soporte</small>' }), fresh, x]);
        el.qReport = h('button', { type: 'button', 'class': 'ahp-q', html: ICON.bug + '<span>Reportar incidencia</span>' });
        el.qTicket = h('button', { type: 'button', 'class': 'ahp-q', html: ICON.ticket + '<span>Abrir ticket</span>' });
        el.quick = h('div', { 'class': 'ahp-quick', style: 'display:none' }, [el.qReport, el.qTicket]);
        el.qReport.addEventListener('click', function () { if (!el.feed.querySelector('.ahp-qf')) { quickForm('report'); } });
        el.qTicket.addEventListener('click', function () { if (!el.feed.querySelector('.ahp-qf')) { quickForm('ticket'); } });
        var foot = h('div', { 'class': 'ahp-foot' }, [el.input, el.send]);

        el.panel = h('aside', { 'class': 'ahp', role: 'dialog', 'aria-modal': 'false', 'aria-label': 'Ayuda' }, [
            head, el.quick, el.feed, foot, h('div', { 'class': 'ahp-note', text: 'Las respuestas salen de la documentación oficial.' })
        ]);

        x.addEventListener('click', close);
        el.scrim.addEventListener('click', close);
        fresh.addEventListener('click', function () { if (!busy) { begin(); } });
        el.send.addEventListener('click', submit);
        el.input.addEventListener('input', grow);
        el.input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); submit(); }
        });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && el.panel.classList.contains('on')) { close(); } });

        document.body.appendChild(el.scrim);
        document.body.appendChild(el.panel);
    }

    function open(opts) {
        opts = opts || {};
        if (!el.panel) { build(); }
        // Dos cuadros seguidos: primero se inserta, luego se activa para que la transición corra.
        requestAnimationFrame(function () { el.panel.classList.add('on'); el.scrim.classList.add('on'); });
        if (!started) { started = true; begin(opts.area); }
        setTimeout(function () { el.input.focus(); }, 230);
    }

    function close() {
        if (!el.panel) { return; }
        el.panel.classList.remove('on');
        el.scrim.classList.remove('on');
    }

    window.AeroHelp = { open: open, close: close };

    // Sin el botón de Soporte rápido de Community, la ayuda trae el suyo.
    window.addEventListener('load', function () {
        if (document.querySelector('.aco-fab')) { return; }
        var fab = h('button', { type: 'button', 'class': 'ahp-fab', 'aria-label': 'Ayuda', html: ICON.help + '<span>Ayuda</span>' });
        fab.addEventListener('click', function () { open(); });
        document.body.appendChild(fab);
    });
})();
