/* Service worker de la PWA de chat. La API nunca se cachea: los datos son de una sola sesión. */
var VERSION = 'aero-chat-v7';
var ASSETS = __ASSETS__;

self.addEventListener('install', function (e) {
    e.waitUntil(caches.open(VERSION).then(function (c) { return c.addAll(ASSETS); }).then(function () { return self.skipWaiting(); }));
});

self.addEventListener('activate', function (e) {
    e.waitUntil(caches.keys().then(function (keys) {
        return Promise.all(keys.filter(function (k) { return k !== VERSION; }).map(function (k) { return caches.delete(k); }));
    }).then(function () { return self.clients.claim(); }));
});

self.addEventListener('fetch', function (e) {
    var req = e.request, url = new URL(req.url);
    if (req.method !== 'GET' || url.origin !== location.origin || url.pathname.indexOf('/api/') === 0) return;

    // Página: red primero; sin red, el shell guardado.
    if (req.mode === 'navigate') {
        e.respondWith(fetch(req).then(function (res) {
            var copy = res.clone(); caches.open(VERSION).then(function (c) { c.put(req, copy); });
            return res;
        }).catch(function () { return caches.match(req).then(function (r) { return r || caches.match('/'); }); }));
        return;
    }

    // Recursos con ?v=: caché primero.
    if (url.pathname.indexOf('/themes/whatsapp/') === 0) {
        e.respondWith(caches.match(req).then(function (r) {
            return r || fetch(req).then(function (res) { var copy = res.clone(); caches.open(VERSION).then(function (c) { c.put(req, copy); }); return res; });
        }));
    }
});
