<?php
$fmt = fn ($n) => number_format($n, 0, ',', '.');
?>
<style>
.wl{max-width:980px}
.wl-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:22px}
.wl-card{border:1px solid #e0e6ed;border-radius:8px;padding:14px 16px;background:#fff}
.wl-card .lbl{display:flex;align-items:center;gap:7px;font-size:12px;color:#6b7785}
.wl-dot{width:10px;height:10px;border-radius:50%;display:inline-block;flex:0 0 auto}
.wl-card .num{font-size:28px;font-weight:700;line-height:1.2;margin-top:4px}
.wl-card .sub{font-size:11px;color:#8492a6}
.wl-tabs{display:flex;gap:4px;border-bottom:1px solid #e0e6ed;margin-bottom:18px}
.wl-tabs button{border:0;background:none;padding:9px 16px;font-weight:600;color:#6b7785;border-bottom:2px solid transparent;cursor:pointer}
.wl-tabs button.on{color:#2b3a4a;border-color:#3b82f6}
.wl-amounts{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px}
.wl-amounts button{border:1px solid #cbd5e1;background:#fff;border-radius:8px;padding:9px 18px;font-weight:700;cursor:pointer}
.wl-amounts button.on{background:#3b82f6;border-color:#3b82f6;color:#fff}
.wl-table{width:100%;border-collapse:collapse}
.wl-table th{font-size:11px;text-transform:uppercase;color:#8492a6;text-align:left;padding:6px 8px;border-bottom:1px solid #e0e6ed}
.wl-table td{padding:9px 8px;border-bottom:1px solid #f0f3f7;vertical-align:middle}
.wl-table input[type=number]{width:90px}
.wl-note{font-size:12px;color:#6b7785}
.wl-warn{color:#b45309}
.wl-ok{color:#15803d;font-weight:600}
.wl-bad{color:#b91c1c}
.wl-qr{display:flex;gap:22px;align-items:center;flex-wrap:wrap;border:1px solid #e0e6ed;border-radius:10px;padding:18px;background:#fff}
.wl-qr img{width:190px;height:190px;background:#fff;border:1px solid #e0e6ed;border-radius:8px;padding:6px}
.wl-pos{color:#15803d;font-weight:600}.wl-neg{color:#b91c1c;font-weight:600}
[hidden]{display:none!important}
</style>

<div class="wl">
<?php if (!$tenantId): ?>
    <div class="callout callout-info"><div class="header"><h3>Elige un sitio</h3><p>Selecciona un sitio (tenant) para ver sus monedas.</p></div></div>
<?php else: ?>

    <div class="wl-cards">
        <?php foreach ($types as $t): ?>
            <div class="wl-card">
                <div class="lbl"><span class="wl-dot" style="background:<?= e($t['color']) ?>"></span><?= e($t['label']) ?></div>
                <div class="num" data-bal="<?= e($t['code']) ?>"><?= $fmt($t['balance']) ?></div>
                <div class="sub"><?= $t['price'] > 0 ? '≈ Bs ' . number_format($t['balance'] * $t['price'], 2, ',', '.') : '' ?></div>
            </div>
        <?php endforeach ?>
    </div>

    <div class="wl-tabs">
        <button type="button" class="on" data-tab="recharge">Recargar</button>
        <button type="button" data-tab="exchange">Intercambiar</button>
        <button type="button" data-tab="history">Movimientos</button>
    </div>

    <!-- RECARGAR -->
    <div data-pane="recharge">
        <div id="wl-form">
            <p class="wl-note" style="margin-bottom:8px">1. Elige cuánto quieres recargar (Bs)</p>
            <div class="wl-amounts">
                <?php foreach ($amounts as $a): ?><button type="button" data-amount="<?= $a ?>">Bs <?= $fmt($a) ?></button><?php endforeach ?>
            </div>

            <div id="wl-alloc" hidden>
                <p class="wl-note" style="margin-bottom:8px">2. Reparte el monto entre las monedas que quieras. Ves al instante cuántas recibes.</p>
                <table class="wl-table">
                    <thead><tr><th>Moneda</th><th>Precio</th><th>Bs a gastar</th><th>Recibes</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($types as $t): if (!$t['buyable']) continue; ?>
                        <tr data-code="<?= e($t['code']) ?>" data-price="<?= $t['price'] ?>">
                            <td><span class="wl-dot" style="background:<?= e($t['color']) ?>"></span> <?= e($t['label']) ?></td>
                            <td>Bs <?= number_format($t['price'], 4, ',', '.') ?></td>
                            <td><input type="number" class="form-control" min="0" step="1" value="0" data-bob></td>
                            <td><strong data-coins>0</strong> <span class="wl-note" data-round></span></td>
                            <td><a href="javascript:;" data-all>todo aquí</a></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
                <p id="wl-status" style="margin:12px 0"></p>
                <button type="button" class="btn btn-primary" id="wl-pay" disabled>Pagar con QR</button>
                <p class="wl-note" style="margin-top:8px">Se cobra el monto completo. Cada moneda se entrega en unidades enteras; lo que no alcanza para una moneda más se muestra como redondeo.</p>
            </div>
        </div>

        <div id="wl-qr" hidden>
            <div class="wl-qr">
                <img id="wl-qr-img" alt="QR de pago" hidden>
                <div>
                    <div style="font-size:26px;font-weight:700">Bs <span id="wl-qr-amount"></span></div>
                    <div id="wl-qr-lines" class="wl-note" style="margin:6px 0 10px"></div>
                    <p>Escanea con tu app bancaria. Acreditamos las monedas apenas se confirme el pago.</p>
                    <p id="wl-qr-state" class="wl-note">Esperando el pago… vence en <strong id="wl-qr-left"></strong></p>
                    <a href="javascript:;" id="wl-qr-cancel" class="wl-note">Cancelar y elegir otro monto</a>
                </div>
            </div>
        </div>
    </div>

    <!-- INTERCAMBIAR -->
    <div data-pane="exchange" hidden>
        <p class="wl-note" style="margin-bottom:12px">Cambia monedas entre sí con una comisión de <strong><?= rtrim(rtrim(number_format($feePercent, 2, '.', ''), '0'), '.') ?>%</strong> sobre lo que entregas. El oro no se intercambia.</p>
        <div class="form-inline" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-bottom:14px">
            <div><label>Entrego</label>
                <select id="ex-from" class="form-control custom-select">
                    <?php foreach ($types as $t): if (!$t['exchangeable']) continue; ?>
                        <option value="<?= e($t['code']) ?>"><?= e($t['label']) ?> (tienes <?= $fmt($t['balance']) ?>)</option>
                    <?php endforeach ?>
                </select></div>
            <div><label>Cantidad</label><input id="ex-amount" type="number" min="1" step="1" class="form-control" style="width:130px"></div>
            <div><label>Recibo</label>
                <select id="ex-to" class="form-control custom-select">
                    <?php foreach ($types as $t): if (!$t['exchangeable']) continue; ?>
                        <option value="<?= e($t['code']) ?>"><?= e($t['label']) ?></option>
                    <?php endforeach ?>
                </select></div>
        </div>
        <p id="ex-quote" style="min-height:22px"></p>
        <button type="button" class="btn btn-primary" id="ex-go" disabled>Intercambiar</button>
    </div>

    <!-- MOVIMIENTOS -->
    <div data-pane="history" hidden>
        <table class="wl-table">
            <thead><tr><th>Fecha</th><th>Tipo</th><th>Moneda</th><th style="text-align:right">Cantidad</th><th style="text-align:right">Saldo</th><th>Detalle</th></tr></thead>
            <tbody>
            <?php foreach ($movements as $m): ?>
                <tr>
                    <td class="wl-note"><?= e($m->created_at?->format('d/m/Y H:i')) ?></td>
                    <td><?= e($kindLabels[$m->kind] ?? $m->kind) ?></td>
                    <td><span class="wl-dot" style="background:<?= e($m->creditType->color ?? '#999') ?>"></span> <?= e($m->creditType->label ?? '') ?></td>
                    <td style="text-align:right" class="<?= $m->delta >= 0 ? 'wl-pos' : 'wl-neg' ?>"><?= ($m->delta > 0 ? '+' : '') . $fmt($m->delta) ?></td>
                    <td style="text-align:right"><?= $fmt($m->balance_after) ?></td>
                    <td class="wl-note"><?= e($m->reason) ?></td>
                </tr>
            <?php endforeach ?>
            <?php if ($movements->isEmpty()): ?><tr><td colspan="6" class="wl-note">Aún no hay movimientos.</td></tr><?php endif ?>
            </tbody>
        </table>
    </div>

<script>
(function () {
    var root = document.querySelector('.wl');
    if (!root) return;
    var $ = function (s, c) { return (c || root).querySelector(s); }, $$ = function (s, c) { return Array.prototype.slice.call((c || root).querySelectorAll(s)); };
    var fmt = function (n) { return Math.round(n).toLocaleString('es-BO'); };
    var amount = 0, purchaseId = null, timers = [];

    // pestañas
    $$('[data-tab]').forEach(function (b) {
        b.addEventListener('click', function () {
            $$('[data-tab]').forEach(function (x) { x.classList.toggle('on', x === b); });
            $$('[data-pane]').forEach(function (p) { p.hidden = p.getAttribute('data-pane') !== b.getAttribute('data-tab'); });
        });
    });

    // ---- recarga ----
    function rows() { return $$('#wl-alloc tbody tr'); }
    function recalc() {
        var sum = 0, allOk = true, any = false;
        rows().forEach(function (tr) {
            var bob = Math.max(0, parseFloat($('[data-bob]', tr).value) || 0), price = parseFloat(tr.getAttribute('data-price'));
            var coins = bob > 0 ? Math.floor(bob / price + 1e-9) : 0;
            $('[data-coins]', tr).textContent = fmt(coins);
            $('[data-round]', tr).textContent = bob > 0 && coins >= 1 ? '(redondeo Bs ' + (bob - coins * price).toFixed(2) + ')' : (bob > 0 ? '(no alcanza para 1)' : '');
            if (bob > 0) { any = true; sum += bob; if (coins < 1) allOk = false; }
        });
        var left = amount - sum, st = $('#wl-status');
        if (left > 0.001) { st.className = 'wl-warn'; st.textContent = 'Faltan Bs ' + left.toFixed(2) + ' por repartir.'; }
        else if (left < -0.001) { st.className = 'wl-bad'; st.textContent = 'Te pasaste por Bs ' + (-left).toFixed(2) + '.'; }
        else if (!allOk) { st.className = 'wl-bad'; st.textContent = 'Alguna moneda no alcanza para 1 unidad.'; }
        else { st.className = 'wl-ok'; st.textContent = 'Listo: Bs ' + amount + ' repartidos.'; }
        $('#wl-pay').disabled = !(any && allOk && Math.abs(left) <= 0.001);
    }
    $$('[data-amount]').forEach(function (b) {
        b.addEventListener('click', function () {
            $$('[data-amount]').forEach(function (x) { x.classList.toggle('on', x === b); });
            amount = parseInt(b.getAttribute('data-amount'), 10);
            $('#wl-alloc').hidden = false;
            var first = true;
            rows().forEach(function (tr) { $('[data-bob]', tr).value = first ? amount : 0; first = false; });
            recalc();
        });
    });
    rows().forEach(function (tr) {
        $('[data-bob]', tr).addEventListener('input', recalc);
        $('[data-all]', tr).addEventListener('click', function () {
            rows().forEach(function (r) { $('[data-bob]', r).value = r === tr ? amount : 0; }); recalc();
        });
    });

    function stopTimers() { timers.forEach(clearInterval); timers = []; }
    function showForm() { stopTimers(); purchaseId = null; $('#wl-qr').hidden = true; $('#wl-form').hidden = false; }

    $('#wl-pay').addEventListener('click', function () {
        var alloc = {};
        rows().forEach(function (tr) { var v = parseFloat($('[data-bob]', tr).value) || 0; if (v > 0) alloc[tr.getAttribute('data-code')] = v; });
        var btn = this; btn.disabled = true;
        window.jQuery.request('onCreatePurchase', {
            data: { amount: amount, alloc: alloc },
            success: function (d) {
                purchaseId = d.id;
                $('#wl-form').hidden = true; $('#wl-qr').hidden = false;
                $('#wl-qr-amount').textContent = d.amount;
                $('#wl-qr-lines').textContent = d.lines.map(function (l) { return fmt(l.coins) + ' ' + l.label.replace('Monedas de ', ''); }).join(' · ');
                var img = $('#wl-qr-img'); if (d.qr_image) { img.src = d.qr_image; img.hidden = false; }
                var exp = new Date(d.expires_at).getTime();
                var tick = function () { var s = Math.max(0, Math.round((exp - Date.now()) / 1000)); $('#wl-qr-left').textContent = Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2); if (s === 0) { $('#wl-qr-state').textContent = 'El QR venció. Vuelve a intentarlo.'; stopTimers(); } };
                tick(); timers.push(setInterval(tick, 1000));
                timers.push(setInterval(function () {
                    window.jQuery.request('onCheckPurchase', { data: { id: purchaseId }, success: function (r) {
                        if (r.status === 'paid') { stopTimers(); $('#wl-qr-state').innerHTML = '<span class="wl-ok">¡Pago recibido! Acreditando tus monedas…</span>'; setTimeout(function () { location.reload(); }, 1500); }
                        else if (r.status === 'review') { stopTimers(); $('#wl-qr-state').innerHTML = '<span class="wl-warn">Recibimos un pago menor al monto. Soporte lo revisará.</span>'; }
                    } });
                }, 4000));
            },
            complete: function () { btn.disabled = false; }
        });
    });
    $('#wl-qr-cancel').addEventListener('click', function () {
        if (purchaseId) window.jQuery.request('onCancelPurchase', { data: { id: purchaseId } });
        showForm();
    });

    // ---- intercambio ----
    var qTimer = null, canGo = false;
    function quote() {
        clearTimeout(qTimer);
        qTimer = setTimeout(function () {
            var out = $('#ex-quote'), go = $('#ex-go'); go.disabled = true; canGo = false;
            var amt = parseInt($('#ex-amount').value, 10) || 0;
            if (!amt) { out.textContent = ''; return; }
            window.jQuery.request('onQuoteExchange', { data: { from: $('#ex-from').value, to: $('#ex-to').value, amount: amt }, handleErrorMessage: function () {}, success: function (q) {
                if (q.error) { out.className = 'wl-bad'; out.textContent = q.error; return; }
                out.className = ''; out.innerHTML = 'Comisión <strong>' + fmt(q.fee) + '</strong> (' + q.fee_percent + '%) · Recibirás <strong style="font-size:18px">' + fmt(q.received) + '</strong>';
                if (q.received < 1) { out.className = 'wl-bad'; out.textContent = 'Muy poco: no alcanza para recibir 1 moneda.'; return; }
                canGo = true; go.disabled = false;
            } });
        }, 250);
    }
    ['ex-from', 'ex-to', 'ex-amount'].forEach(function (id) { $('#' + id).addEventListener('input', quote); $('#' + id).addEventListener('change', quote); });
    $('#ex-go').addEventListener('click', function () {
        if (!canGo || !confirm('¿Confirmar el intercambio? No se puede deshacer.')) return;
        this.disabled = true;
        window.jQuery.request('onExchange', { data: { from: $('#ex-from').value, to: $('#ex-to').value, amount: parseInt($('#ex-amount').value, 10) }, success: function () { location.reload(); } });
    });
})();
</script>
<?php endif ?>
</div>
