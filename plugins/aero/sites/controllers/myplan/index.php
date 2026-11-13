<div id="myplan-body">
    <?= $this->makePartial('body') ?>
</div>
<script>
// Si hay un QR pendiente, consulta cada 10 s: al confirmarse el pago la pantalla se actualiza sola.
(function () {
    var hasQr = <?= $renewal ? 'true' : 'false' ?>;
    if (!hasQr) return;
    var t = setInterval(function () {
        if (document.hidden) return;
        if (!document.getElementById('myplan-body')) { clearInterval(t); return; }
        $.request('onRefresh', { loading: false });
    }, 10000);
})();
</script>
