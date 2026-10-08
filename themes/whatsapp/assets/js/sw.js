/* Service worker de la PWA de chat. La API nunca se cachea: los datos son de una sola sesión. */
var VERSION = 'aero-chat-v26';
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

// ---------- Web Push (Aero.Notify) ----------
self.addEventListener('push', function (e) {
    var d = {};
    try { d = e.data ? e.data.json() : {}; } catch (err) { d = { body: e.data ? e.data.text() : '' }; }
    var log = function (r) { return caches.open('aero-diag').then(function (c) { return c.put('/__last_push', new Response(JSON.stringify(r))); }).catch(function () {}); };
    e.waitUntil(self.registration.showNotification(d.title || 'Chat', {
        body: d.body || '',
        icon: __ICON__,
        badge: __ICON__,
        tag: d.tag || undefined,
        renotify: !!d.tag,
        data: { url: d.url || '/' },
    }).then(function () { return log({ at: Date.now(), shown: true, title: d.title }); })
      .catch(function (err) { return log({ at: Date.now(), shown: false, error: String(err && err.message || err) }); }));
});

self.addEventListener('notificationclick', function (e) {
    e.notification.close();
    var target = new URL((e.notification.data && e.notification.data.url) || '/', self.location.origin).href;
    e.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
        for (var i = 0; i < list.length; i++) {
            if (list[i].url.indexOf(self.location.origin) === 0 && 'focus' in list[i]) {
                // Ventana ya abierta: se le pide abrir el chat en vez de solo enfocarla.
                list[i].postMessage({ type: 'open-url', url: target });
                return list[i].focus();
            }
        }
        return clients.openWindow(target);
    }));
});
