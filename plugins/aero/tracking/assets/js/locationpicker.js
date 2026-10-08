/*
 * Aero · selector de ubicación reutilizable (frontend y backend).
 *
 * Markup mínimo (se inicializa solo, también si el nodo aparece después,
 * p.ej. dentro de un repeater):
 *
 *   <div data-aero-location-picker
 *        data-lat-input="#lat" data-lng-input="#lng" data-acc-input="#acc"></div>
 *
 * Atributos opcionales: data-lat / data-lng (valor inicial; si no hay, se
 * lee de los inputs), data-zoom, data-height (px), data-autolocate="0",
 * data-default-lat / data-default-lng (centro si no hay nada; Bolivia),
 * data-provider="osm" (ver AeroLocationPicker.providers).
 *
 * Eventos: 'aero:location' en el nodo, con detail {lat, lng, accuracy, source}.
 * source = 'gps' (detectada) | 'manual' (el usuario movió el pin).
 *
 * Proveedores de mapa: hoy OSM + satélite Esri, ambos sin API key. Para
 * Google Maps se registra otro proveedor en AeroLocationPicker.providers
 * (función que recibe L y devuelve {base, satellite}).
 */
(function (w, d) {
    'use strict';

    var LEAFLET = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet';
    var pending = null;

    function ensureLeaflet(cb) {
        if (w.L && w.L.map) return cb();
        if (pending) return pending.push(cb);
        pending = [cb];
        var css = d.createElement('link');
        css.rel = 'stylesheet'; css.href = LEAFLET + '.css'; d.head.appendChild(css);
        var js = d.createElement('script');
        js.src = LEAFLET + '.js';
        js.onload = function () { var q = pending; pending = null; q.forEach(function (f) { f(); }); };
        d.head.appendChild(js);
    }

    var providers = {
        osm: function (L) {
            return {
                base: L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '© OpenStreetMap'}),
                satellite: L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {maxZoom: 19, attribution: 'Esri, Maxar'})
            };
        }
    };

    function num(v) { var n = parseFloat(v); return isNaN(n) ? null : n; }
    function $(sel) { return sel ? d.querySelector(sel) : null; }
    function fire(input) {
        if (!input) return;
        ['input', 'change'].forEach(function (t) { input.dispatchEvent(new Event(t, {bubbles: true})); });
    }

    var PIN = '<svg width="28" height="38" viewBox="0 0 28 38"><path d="M14 0C6.3 0 0 6.2 0 13.8 0 24 14 38 14 38s14-14 14-24.2C28 6.2 21.7 0 14 0z" fill="#e5392d"/><circle cx="14" cy="14" r="5" fill="#fff"/></svg>';

    function mount(el) {
        if (el._alp) return;
        el._alp = true;

        var latIn = $(el.dataset.latInput), lngIn = $(el.dataset.lngInput), accIn = $(el.dataset.accInput);
        var lat = num(el.dataset.lat), lng = num(el.dataset.lng);
        if (lat === null && latIn) lat = num(latIn.value);
        if (lng === null && lngIn) lng = num(lngIn.value);
        var hasPoint = lat !== null && lng !== null;
        var autolocate = el.dataset.autolocate !== '0';

        el.classList.add('alp');
        el.innerHTML = '<div class="alp-map" style="height:' + (parseInt(el.dataset.height, 10) || 320) + 'px"></div>'
            + '<div class="alp-bar"><span class="alp-status">Cargando mapa…</span><span class="alp-btns">'
            + '<button type="button" class="alp-btn" data-act="locate">📍 Mi ubicación</button>'
            + '<button type="button" class="alp-btn" data-act="layer">🛰 Satélite</button></span></div>';
        var status = el.querySelector('.alp-status');

        ensureLeaflet(function () {
            var L = w.L;
            var prov = (providers[el.dataset.provider] || providers.osm)(L);
            var center = hasPoint ? [lat, lng]
                : [num(el.dataset.defaultLat) !== null ? num(el.dataset.defaultLat) : -16.5, num(el.dataset.defaultLng) !== null ? num(el.dataset.defaultLng) : -64.7];
            var zoom = parseInt(el.dataset.zoom, 10) || (hasPoint ? 17 : 6);

            var map = L.map(el.querySelector('.alp-map'), {center: center, zoom: zoom, layers: [prov.base]});
            var marker = L.marker(center, {
                draggable: true,
                icon: L.divIcon({className: 'alp-pin', html: PIN, iconSize: [28, 38], iconAnchor: [14, 38]})
            });
            var circle = null, touched = false, satellite = false;
            if (hasPoint) marker.addTo(map);

            function write(p, acc, source) {
                var la = p.lat.toFixed(6), ln = p.lng.toFixed(6);
                if (latIn) { latIn.value = la; fire(latIn); }
                if (lngIn) { lngIn.value = ln; fire(lngIn); }
                if (accIn) { accIn.value = acc == null ? '' : Math.round(acc); fire(accIn); }
                status.textContent = la + ', ' + ln + (acc != null ? ' · precisión ±' + Math.round(acc) + ' m' : ' · ajustada a mano');
                el.dispatchEvent(new CustomEvent('aero:location', {bubbles: true, detail: {lat: +la, lng: +ln, accuracy: acc, source: source}}));
            }

            function place(p, acc, source) {
                if (!map.hasLayer(marker)) marker.addTo(map);
                marker.setLatLng(p);
                if (circle) { map.removeLayer(circle); circle = null; }
                if (acc != null) circle = L.circle(p, {radius: acc, weight: 1, color: '#3b82f6', fillOpacity: .12, interactive: false}).addTo(map);
                write(L.latLng(p), acc, source);
            }

            marker.on('dragend', function () { touched = true; place(marker.getLatLng(), null, 'manual'); });
            map.on('click', function (e) { touched = true; place(e.latlng, null, 'manual'); });

            var watchId = null, btn = el.querySelector('[data-act="locate"]');
            function locate(force) {
                if (!navigator.geolocation) { status.textContent = 'Tu navegador no permite detectar la ubicación: toca el mapa para marcarla.'; return; }
                if (watchId !== null) navigator.geolocation.clearWatch(watchId);
                if (force) touched = false;
                btn.disabled = true;
                status.textContent = 'Detectando tu ubicación…';
                var best = null, done = false;
                function finish() {
                    if (done) return; done = true;
                    navigator.geolocation.clearWatch(watchId); watchId = null; btn.disabled = false;
                    if (!best && !hasPoint && !marker._map) status.textContent = 'No pudimos detectar tu ubicación: toca el mapa para marcarla.';
                }
                // Se sigue el GPS unos segundos y se queda con la lectura más precisa.
                watchId = navigator.geolocation.watchPosition(function (pos) {
                    if (touched) return finish();
                    var acc = pos.coords.accuracy;
                    if (!best || acc < best) {
                        best = acc;
                        var p = L.latLng(pos.coords.latitude, pos.coords.longitude);
                        place(p, acc, 'gps');
                        map.setView(p, acc > 200 ? 15 : acc > 50 ? 17 : 18);
                    }
                    if (acc <= 20) finish();
                }, function () { finish(); }, {enableHighAccuracy: true, timeout: 15000, maximumAge: 0});
                setTimeout(finish, 12000);
            }

            el.addEventListener('click', function (e) {
                var b = e.target.closest('[data-act]');
                if (!b) return;
                if (b.dataset.act === 'locate') locate(true);
                if (b.dataset.act === 'layer') {
                    satellite = !satellite;
                    map.removeLayer(satellite ? prov.base : prov.satellite);
                    (satellite ? prov.satellite : prov.base).addTo(map);
                    b.textContent = satellite ? '🗺 Mapa' : '🛰 Satélite';
                }
            });

            if (hasPoint) status.textContent = 'Arrastra el pin o toca el mapa para ajustar la posición.';
            else { status.textContent = 'Toca el mapa para marcar la ubicación exacta.'; if (autolocate) locate(false); }

            // El mapa nace a veces oculto (pestañas, repeaters, modales): recalcular al mostrarse.
            if (w.ResizeObserver) new ResizeObserver(function () { map.invalidateSize(); }).observe(el);
            el._alpMap = map;
        });
    }

    function scan(root) {
        (root || d).querySelectorAll('[data-aero-location-picker]').forEach(mount);
    }

    function boot() {
        scan();
        if (w.MutationObserver) {
            new MutationObserver(function (muts) {
                muts.forEach(function (m) { m.addedNodes.forEach(function (n) {
                    if (n.nodeType !== 1) return;
                    if (n.matches('[data-aero-location-picker]')) mount(n); else scan(n);
                }); });
            }).observe(d.body, {childList: true, subtree: true});
        }
    }

    w.AeroLocationPicker = {mount: mount, scan: scan, providers: providers};
    if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', boot); else boot();
})(window, document);
