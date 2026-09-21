<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/tracking/livemap') ?>">Tracking</a></li><li>Mapa en vivo</li></ul>
<?php Block::endPut() ?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css">
<div style="display:flex;gap:12px;height:calc(100vh - 170px);min-height:420px">
    <div id="dl-list" style="width:260px;overflow:auto;background:#fff;border:1px solid #dde;border-radius:4px"></div>
    <div id="dl-map" style="flex:1;border:1px solid #dde;border-radius:4px"></div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
<script>
(function () {
    var url = <?= json_encode(Backend::url('aero/tracking/livemap/positions')) ?>;
    var map = L.map('dl-map').setView([-16.5, -64.7], 6); // Bolivia
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '© OpenStreetMap'}).addTo(map);
    var markers = {}, fitted = false, list = document.getElementById('dl-list');

    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
    function ago(iso) {
        if (!iso) return 'sin señal';
        var s = Math.max(0, (Date.now() - new Date(iso)) / 1000);
        return s < 60 ? 'hace ' + Math.round(s) + ' s' : s < 3600 ? 'hace ' + Math.round(s / 60) + ' min' : 'hace ' + Math.round(s / 3600) + ' h';
    }

    function refresh() {
        fetch(url, {credentials: 'same-origin', headers: {'Accept': 'application/json'}})
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var seen = {}, bounds = [], html = '';
                data.assets.forEach(function (a) {
                    var color = a.online ? '#2e9e5b' : '#999';
                    html += '<div data-id="' + a.id + '" style="padding:8px 10px;border-bottom:1px solid #eef;cursor:pointer">'
                        + '<b style="color:' + color + '">●</b> <b>' + esc(a.name) + '</b> <small>' + esc(a.type) + '</small><br>'
                        + '<small>' + (a.position ? ago(a.position.recorded_at) : 'sin posición')
                        + (a.position && a.position.speed != null ? ' · ' + Math.round(a.position.speed * 3.6) + ' km/h' : '')
                        + (a.position && a.position.battery != null ? ' · 🔋' + a.position.battery + '%' : '') + '</small>'
                        + (a.open_jobs.length ? '<br><small>' + a.open_jobs.map(esc).join(', ') + '</small>' : '') + '</div>';
                    if (!a.position) return;
                    seen[a.id] = true;
                    var ll = [a.position.lat, a.position.lng];
                    bounds.push(ll);
                    var popup = '<b>' + esc(a.name) + '</b><br>' + esc(a.code || '') + '<br>' + ago(a.position.recorded_at);
                    if (markers[a.id]) { markers[a.id].setLatLng(ll).setStyle({color: color, fillColor: color}).setPopupContent(popup); }
                    else { markers[a.id] = L.circleMarker(ll, {radius: 9, weight: 2, color: color, fillColor: color, fillOpacity: .8}).addTo(map).bindPopup(popup); }
                });
                Object.keys(markers).forEach(function (id) { if (!seen[id]) { map.removeLayer(markers[id]); delete markers[id]; } });
                list.innerHTML = html || '<div style="padding:12px"><small>No hay activos. Crea uno en Activos.</small></div>';
                if (!fitted && bounds.length) { map.fitBounds(bounds, {maxZoom: 15, padding: [40, 40]}); fitted = true; }
            }).catch(function () {});
    }

    list.addEventListener('click', function (e) {
        var row = e.target.closest('[data-id]');
        if (row && markers[row.dataset.id]) { map.setView(markers[row.dataset.id].getLatLng(), 16); markers[row.dataset.id].openPopup(); }
    });
    refresh();
    setInterval(refresh, 5000);
})();
</script>
