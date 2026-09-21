/* Entorno del demo: sin persistencia, sin permisos del navegador y sin tocar la URL de la página que lo aloja. */
(function () {
    'use strict';
    var mem = {};
    var storage = {
        getItem: function (k) { return Object.prototype.hasOwnProperty.call(mem, k) ? mem[k] : null; },
        setItem: function (k, v) { mem[k] = String(v); },
        removeItem: function (k) { delete mem[k]; },
        clear: function () { mem = {}; },
    };
    // Sesión ya iniciada en el espacio ficticio.
    mem['aero.chat.token.altiplano'] = 'demo-token';
    try { Object.defineProperty(window, 'localStorage', { value: storage, configurable: true }); } catch (e) { /* se usa el real */ }

    // El demo no debe cambiar la URL de la página que lo incrusta.
    ['pushState', 'replaceState'].forEach(function (fn) {
        var orig = history[fn].bind(history);
        history[fn] = function (state, title) { try { orig(state, title || ''); } catch (e) { /* sandbox sin historial */ } };
    });

    window.confirm = function () { return true; };

    // Sin service worker: la PWA lo registra y escucha mensajes; aquí no existe.
    try {
        Object.defineProperty(navigator, 'serviceWorker', { configurable: true, value: {
            register: function () { return Promise.resolve({}); }, addEventListener: function () {}, ready: new Promise(function () {}),
        } });
    } catch (e) { /* ignora */ }

    // GPS fijo (Plaza Murillo, La Paz) para "Enviar mi ubicación".
    try {
        Object.defineProperty(navigator, 'geolocation', { configurable: true, value: {
            getCurrentPosition: function (ok) { setTimeout(function () { ok({ coords: { latitude: -16.4956, longitude: -68.1339 } }); }, 400); },
        } });
    } catch (e) { /* ignora */ }

    // Micrófono simulado: "graba" un tono corto para poder probar la nota de voz.
    try {
        Object.defineProperty(navigator, 'mediaDevices', { configurable: true, value: { getUserMedia: function () { return Promise.resolve({ getTracks: function () { return []; } }); } } });
        window.MediaRecorder = function (stream) { this.mimeType = 'audio/wav'; this.state = 'inactive'; };
        window.MediaRecorder.isTypeSupported = function () { return true; };
        window.MediaRecorder.prototype.start = function () { this.state = 'recording'; };
        window.MediaRecorder.prototype.stop = function () {
            this.state = 'inactive';
            if (this.ondataavailable) this.ondataavailable({ data: window.AltiplanoDemo.wav() });
            if (this.onstop) this.onstop();
        };
    } catch (e) { /* ignora */ }
})();
