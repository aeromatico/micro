<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/gym/liveboard') ?>">Gimnasio</a></li><li>Clases en vivo</li></ul>
<?php Block::endPut() ?>
<style>
    .gb-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px}
    .gb-card{background:color-mix(in srgb, var(--bs-body-color) 5%, var(--bs-body-bg));border:1px solid var(--bs-border-color);border-radius:8px;padding:14px;border-top:5px solid #888}
    .gb-card.live{box-shadow:0 0 0 2px #2e9e5b inset}
    .gb-bar{height:10px;background:color-mix(in srgb, var(--bs-body-color) 12%, var(--bs-body-bg));border-radius:5px;overflow:hidden;margin:8px 0}
    .gb-bar i{display:block;height:100%;background:#2e9e5b}
    .gb-bar i.full{background:#c0392b}
    .gb-list{list-style:none;margin:8px 0 0;padding:0;font-size:13px}
    .gb-list li{display:flex;justify-content:space-between;padding:3px 0;border-bottom:1px solid var(--bs-border-color)}
    .gb-attended{color:#2e9e5b}.gb-waitlist{color:#d9822b}.gb-no_show{color:var(--bs-secondary-color);text-decoration:line-through}
</style>
<div style="margin-bottom:10px;color:var(--bs-secondary-color)">Se actualiza solo cada 3 s · <span id="gb-clock">—</span></div>
<div id="gb" class="gb-grid"><div class="text-muted">Cargando…</div></div>
<script>
(function () {
    var url = <?= json_encode(Backend::url('aero/gym/liveboard/data')) ?>, version = '', box = document.getElementById('gb');
    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
    var labels = {booked: '', attended: '✔', waitlist: 'en espera', no_show: 'no vino'};

    function render(sessions) {
        if (!sessions.length) { box.innerHTML = '<div class="text-muted">No hay clases para hoy.</div>'; return; }
        box.innerHTML = sessions.map(function (s) {
            var pct = s.capacity ? Math.min(100, Math.round(s.booked / s.capacity * 100)) : 0;
            var rows = s.members.map(function (m) {
                var act = '';
                if (m.status === 'booked') {
                    act = ' <a href="#" data-request="onCheckIn" data-request-data="booking_id: ' + m.booking + '" data-request-success="window.gymBoardRefresh()">asistió</a>'
                        + ' · <a href="#" data-request="onNoShow" data-request-data="booking_id: ' + m.booking + '" data-request-success="window.gymBoardRefresh()">no vino</a>';
                }
                return '<li><span class="gb-' + m.status + '">' + esc(m.name) + ' ' + (labels[m.status] || '') + '</span><span>' + act + '</span></li>';
            }).join('');
            return '<div class="gb-card' + (s.live ? ' live' : '') + '" style="border-top-color:' + (s.color || '#888') + '">'
                + '<b style="font-size:16px">' + esc(s.name) + '</b>' + (s.live ? ' <span class="label label-success">EN CURSO</span>' : '')
                + '<div style="color:var(--bs-secondary-color)">' + s.starts + ' – ' + s.ends + (s.instructor ? ' · ' + esc(s.instructor) : '') + (s.room ? ' · ' + esc(s.room) : '') + '</div>'
                + '<div class="gb-bar"><i class="' + (pct >= 100 ? 'full' : '') + '" style="width:' + pct + '%"></i></div>'
                + '<div><b>' + s.booked + '/' + s.capacity + '</b> inscritos · ' + s.attended + ' asistieron' + (s.waitlist ? ' · <span class="gb-waitlist">' + s.waitlist + ' en espera</span>' : '') + '</div>'
                + '<ul class="gb-list">' + rows + '</ul></div>';
        }).join('');
    }

    function refresh() {
        fetch(url + '?v=' + encodeURIComponent(version), {credentials: 'same-origin', headers: {'Accept': 'application/json'}})
            .then(function (r) { return r.json(); })
            .then(function (d) {
                document.getElementById('gb-clock').textContent = new Date().toLocaleTimeString();
                if (d.changed) { version = d.v; render(d.sessions); }
            }).catch(function () {});
    }
    window.gymBoardRefresh = function () { version = ''; refresh(); };
    refresh();
    setInterval(refresh, 3000);
})();
</script>
