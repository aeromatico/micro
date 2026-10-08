<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/gym/access') ?>">Gimnasio</a></li><li>Control de acceso</li></ul>
<?php Block::endPut() ?>
<?php if ($this->gymQrMode): ?>
<div id="gym-qr-panel" style="max-width:520px;margin:20px auto;text-align:center">
    <div style="color:var(--bs-secondary-color);margin-bottom:10px">Los socios escanean este código con la cámara de su móvil</div>
    <div id="gym-qr-box" style="display:inline-block;background:#fff;padding:16px;border-radius:12px"></div>
    <div style="margin-top:10px;color:var(--bs-secondary-color)">Se renueva en <b id="gym-qr-left">–</b> s</div>
    <ul id="gym-qr-recent" style="list-style:none;padding:0;margin:18px 0 0;text-align:left"></ul>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
(function () {
    var box = document.getElementById('gym-qr-box'), left = document.getElementById('gym-qr-left'),
        list = document.getElementById('gym-qr-recent'), current = '', secs = 0;
    function paint(d) {
        if (d.url !== current) { current = d.url; box.innerHTML = ''; new QRCode(box, {text: d.url, width: 280, height: 280}); }
        secs = d.expires;
        list.innerHTML = d.recent.map(function (r) {
            var li = document.createElement('li');
            li.style.cssText = 'padding:6px 0;border-bottom:1px solid var(--bs-border-color);color:' + (r.granted ? '#2e9e5b' : '#c0392b');
            li.textContent = r.at + '  ' + r.name + (r.granted ? '' : ' — ' + r.reason);
            return li.outerHTML;
        }).join('');
    }
    function load() { $.request('onQr', {success: function (d) { paint(d); }}); }
    setInterval(function () { secs--; left.textContent = Math.max(secs, 0); if (secs <= 0) load(); }, 1000);
    setInterval(load, 5000);
    load();
})();
</script>
<?php endif ?>
<form id="gym-access-form" onsubmit="return false" style="max-width:520px;margin:20px auto">
    <input type="text" name="credential" id="gym-credential" class="form-control input-lg" autocomplete="off" autofocus
           placeholder="Escanee el QR, o escriba carnet o documento, y presione Enter" style="font-size:20px;height:56px">
</form>
<div id="gym-access-result" style="max-width:520px;margin:0 auto"></div>
<script>
(function () {
    var input = document.getElementById('gym-credential');
    input.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        var code = input.value;
        if (!code.trim()) return;
        $.request('onCheck', {data: {credential: code}}).always(function () { input.value = ''; input.focus(); });
    });
    document.addEventListener('click', function () { input.focus(); });
})();
</script>
