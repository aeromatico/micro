/*
 * Aviso de cookies RGPD (aero/rgpd). Sin dependencias.
 *  - Nada no esencial se activa antes de consentir: los scripts de terceros se
 *    marcan <script type="text/plain" data-rgpd="analytics|marketing" src="…">
 *    y este archivo los activa solo cuando hay permiso.
 *  - API: aeroRgpd.has('analytics'), aeroRgpd.open(); evento 'aero:rgpd' en window.
 *  - Cualquier elemento con [data-rgpd-open] reabre las preferencias.
 */
(function () {
    'use strict';
    var KEY = 'aero_rgpd', DAYS = 180;
    var root = document.getElementById('aero-rgpd');
    if (!root) return;

    var version = parseInt(root.dataset.version, 10) || 1;
    var useA = root.dataset.analytics === '1', useM = root.dataset.marketing === '1';
    var box = root.querySelector('.rg-opts');
    var openBtn = document.getElementById('aero-rgpd-open');

    function read() {
        var m = document.cookie.match('(?:^|; )' + KEY + '=([^;]*)');
        if (!m) return null;
        try { return JSON.parse(decodeURIComponent(m[1])); } catch (e) { return null; }
    }
    function write(s) {
        var secure = location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = KEY + '=' + encodeURIComponent(JSON.stringify(s)) + '; Max-Age=' + DAYS * 86400 + '; Path=/; SameSite=Lax' + secure;
    }
    function uuid() {
        if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = Math.random() * 16 | 0; return (c === 'x' ? r : (r & 3 | 8)).toString(16);
        });
    }
    function state() {
        var s = read();
        return s && s.v === version ? s : null;
    }
    function has(cat) {
        var s = state();
        return !!(s && ((cat === 'analytics' && s.a) || (cat === 'marketing' && s.m)));
    }
    function activate() {
        document.querySelectorAll('script[type="text/plain"][data-rgpd]').forEach(function (old) {
            if (old.dataset.rgpdDone || !has(old.dataset.rgpd)) return;
            old.dataset.rgpdDone = '1';
            var s = document.createElement('script');
            for (var i = 0; i < old.attributes.length; i++) {
                var n = old.attributes[i].name;
                if (n !== 'type') s.setAttribute(n, old.attributes[i].value);
            }
            s.text = old.text;
            old.parentNode.insertBefore(s, old.nextSibling);
        });
    }
    function show(withOptions) {
        var s = state() || {};
        root.querySelector('[data-rg="a"]') && (root.querySelector('[data-rg="a"]').checked = !!s.a);
        root.querySelector('[data-rg="m"]') && (root.querySelector('[data-rg="m"]').checked = !!s.m);
        box.hidden = !withOptions;
        root.hidden = false;
        if (openBtn) openBtn.hidden = true;
        var first = root.querySelector('button'); first && first.focus();
    }
    function save(a, m) {
        var prev = read();
        var s = { v: version, a: useA && a ? 1 : 0, m: useM && m ? 1 : 0, id: (prev && prev.id) || uuid(), t: Date.now() };
        write(s);
        root.hidden = true;
        if (openBtn) openBtn.hidden = false;
        saveMode(false);
        if (root.dataset.log === '1') {
            try {
                fetch('/rgpd/consent', {
                    method: 'POST', keepalive: true, credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ id: s.id, a: !!s.a, m: !!s.m })
                });
            } catch (e) {}
        }
        activate();
        window.dispatchEvent(new CustomEvent('aero:rgpd', { detail: { analytics: !!s.a, marketing: !!s.m } }));
    }

    var custBtn = root.querySelector('[data-rg-act="customize"]');
    var custLabel = custBtn ? custBtn.textContent : '';
    function saveMode(on) {
        if (!custBtn) return;
        custBtn.dataset.rgAct = on ? 'save' : 'customize';
        custBtn.textContent = on ? 'Guardar selección' : custLabel;
    }

    root.addEventListener('click', function (e) {
        var act = e.target.closest('[data-rg-act]');
        if (!act) return;
        var what = act.dataset.rgAct;
        if (what === 'accept') return save(true, true);
        if (what === 'reject') return save(false, false);
        if (what === 'customize') { box.hidden = false; return saveMode(true); }
        if (what === 'save') {
            var a = root.querySelector('[data-rg="a"]'), m = root.querySelector('[data-rg="m"]');
            save(!!(a && a.checked), !!(m && m.checked));
        }
    });
    document.addEventListener('keydown', function (e) {
        // Esc cierra el panel solo si ya hay una decisión guardada.
        if (e.key === 'Escape' && !root.hidden && state()) { root.hidden = true; if (openBtn) openBtn.hidden = false; }
    });
    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-rgpd-open]')) { e.preventDefault(); reopen(); }
    });
    if (openBtn) openBtn.addEventListener('click', reopen);

    function reopen() { show(true); saveMode(true); }

    window.aeroRgpd = { has: has, open: reopen, state: state };

    if (state()) { if (openBtn) openBtn.hidden = false; activate(); } else { show(false); }
})();
