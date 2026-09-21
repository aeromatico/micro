<?php
$fmt = fn ($n) => number_format($n, 0, ',', '.');
$bs = fn ($units, $mode = 'floor') => \Aero\Credits\Classes\Money::format((int) abs($units), 2, $mode);
$price = fn ($units) => \Aero\Credits\Classes\Money::price((int) $units);
?>
<style>
/* Solo variables del tema del panel (light/dark): ningún fondo ni color fijo, salvo el blanco del QR. */
.wl{
    --wl-surface: color-mix(in srgb, var(--bs-body-color) 5%, var(--bs-body-bg));
    --wl-hover: color-mix(in srgb, var(--bs-body-color) 9%, var(--bs-body-bg));
    --wl-line: var(--bs-border-color);
    --wl-muted: var(--bs-secondary-color);
    --wl-accent: var(--oc-accent, #3498db);
    --wl-ok: color-mix(in srgb, var(--bs-success, #41b862) 85%, var(--bs-body-color));
    --wl-bad: color-mix(in srgb, var(--bs-danger, #dc3545) 85%, var(--bs-body-color));
    --wl-warn: color-mix(in srgb, var(--bs-warning, #ffc107) 60%, var(--bs-body-color));
    max-width:980px;color:var(--bs-body-color)
}
.wl-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:22px}
.wl-card{border:1px solid var(--wl-line);border-radius:8px;padding:14px 16px;background:var(--wl-surface)}
.wl-card .lbl{display:flex;align-items:center;gap:7px;font-size:12px;color:var(--wl-muted)}
.wl-dot{width:10px;height:10px;border-radius:50%;display:inline-block;flex:0 0 auto;box-shadow:0 0 0 1px color-mix(in srgb,var(--bs-body-color) 25%,transparent)}
.wl-card .num{font-size:28px;font-weight:700;line-height:1.2;margin-top:4px;color:var(--bs-emphasis-color,var(--bs-body-color))}
.wl-card .sub{font-size:11px;color:var(--wl-muted)}
.wl-tabs{display:flex;gap:4px;border-bottom:1px solid var(--wl-line);margin-bottom:18px}
.wl-tabs button{border:0;background:none;padding:9px 16px;font-weight:600;color:var(--wl-muted);border-bottom:2px solid transparent;cursor:pointer}
.wl-tabs button:hover{color:var(--bs-body-color)}
.wl-tabs button.on{color:var(--bs-emphasis-color,var(--bs-body-color));border-color:var(--wl-accent)}
.wl-amounts{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px}
.wl-amounts button{border:1px solid var(--wl-line);background:var(--wl-surface);color:var(--bs-body-color);border-radius:8px;padding:9px 18px;font-weight:700;cursor:pointer}
.wl-amounts button:hover{background:var(--wl-hover)}
.wl-amounts button.on{background:var(--wl-accent);border-color:var(--wl-accent);color:#fff}
.wl-table{width:100%;border-collapse:collapse}
.wl-table th{font-size:11px;text-transform:uppercase;color:var(--wl-muted);text-align:left;padding:6px 8px;border-bottom:1px solid var(--wl-line)}
.wl-table td{padding:9px 8px;border-bottom:1px solid var(--wl-line);vertical-align:middle}
.wl-table tbody tr:hover{background:var(--wl-surface)}
.wl-table input[type=number]{width:90px}
.wl-note{font-size:12px;color:var(--wl-muted)}
.wl-warn{color:var(--wl-warn)}
.wl-ok{color:var(--wl-ok);font-weight:600}
.wl-bad{color:var(--wl-bad)}
.wl-qr{display:flex;gap:22px;align-items:center;flex-wrap:wrap;border:1px solid var(--wl-line);border-radius:10px;padding:18px;background:var(--wl-surface)}
.wl-qr img{width:190px;height:190px;background:#fff;border:1px solid var(--wl-line);border-radius:8px;padding:8px} /* el QR necesita fondo blanco para escanearse */
.wl-pos{color:var(--wl-ok);font-weight:600}.wl-neg{color:var(--wl-bad);font-weight:600}
.wl-card.money{border-color:color-mix(in srgb,var(--wl-ok) 45%,var(--wl-line))}
.wl-card.money .num{color:var(--wl-ok)}
.wl-banner{border:1px solid color-mix(in srgb,var(--wl-ok) 45%,var(--wl-line));background:color-mix(in srgb,var(--wl-ok) 9%,var(--bs-body-bg));border-radius:8px;padding:10px 14px;margin:12px 0}
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
        <div class="wl-card money" title="Dinero disponible: lo que sobra de tus recargas. Cámbialo por cualquier moneda cuando quieras.">
            <div class="lbl"><span class="wl-dot" style="background:#22c55e"></span>Saldo en Bs</div>
            <div class="num" data-wallet>Bs <?= e($walletBs) ?></div>
            <div class="sub">para comprar cualquier moneda</div>
        </div>
    </div>

    <div class="wl-tabs">
        <button type="button" class="on" data-tab="recharge">Recargar</button>
        <button type="button" data-tab="buy">Usar mi saldo en Bs</button>
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
                <p class="wl-note" style="margin-bottom:8px">2. Elige la moneda que quieres comprar con <strong>Bs <span data-amount-label></span></strong>. Cada recarga es de <strong>una sola moneda</strong>; <strong>el cambio que sobre no se pierde: va a tu saldo en Bs</strong>, para comprar cualquier moneda cuando quieras.</p>
                <table class="wl-table">
                    <thead><tr><th>Moneda</th><th>Precio</th><th>Gastas</th><th>Recibes</th><th>Cambio a tu saldo en Bs</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($types as $t): if (!$t['buyable']) continue; ?>
                        <tr data-code="<?= e($t['code']) ?>" data-price-units="<?= \Aero\Credits\Classes\Money::units($t['price']) ?>">
                            <td><span class="wl-dot" style="background:<?= e($t['color']) ?>"></span> <?= e($t['label']) ?></td>
                            <td><?= e($price(\Aero\Credits\Classes\Money::units($t['price']))) ?></td>
                            <td>Bs <span data-amount-label></span></td>
                            <td><strong data-coins>0</strong></td>
                            <td data-change class="wl-note">—</td>
                            <td><button type="button" class="btn btn-primary btn-sm" data-buy>Comprar monedas</button></td>
                        </tr>
                    <?php endforeach ?>
                        <tr data-code="" data-price-units="0">
                            <td><span class="wl-dot" style="background:#22c55e"></span> Saldo en Bs</td>
                            <td class="wl-note">—</td>
                            <td>Bs <span data-amount-label></span></td>
                            <td><strong>Bs <span data-amount-label></span></strong></td>
                            <td class="wl-note">todo a tu saldo</td>
                            <td><button type="button" class="btn btn-default btn-sm" data-buy>Cargar saldo</button></td>
                        </tr>
                    </tbody>
                </table>
                <p class="wl-note" id="wl-buy-error" style="margin-top:10px"></p>
                <p class="wl-note" style="margin-top:8px">Se cobra el monto completo. Las monedas se entregan en unidades enteras al precio de lista.</p>
            </div>

            <div id="wl-custom" style="margin-top:26px;padding-top:18px;border-top:1px solid var(--wl-line)">
                <p class="wl-note" style="margin-bottom:8px"><strong>¿Quieres una cantidad exacta?</strong> Escribe cuántas monedas de <strong>una sola moneda</strong> necesitas y pagas justo lo que cuestan, sin cambio. El monto mínimo de una recarga es <strong>Bs <?= $fmt($minAmount) ?></strong>.</p>
                <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
                    <div><label>Moneda</label>
                        <select id="cx-coin" class="form-control custom-select">
                            <?php foreach ($buyable as $t): ?>
                                <option value="<?= e($t['code']) ?>" data-price-units="<?= $t['priceUnits'] ?>"><?= e($t['label']) ?> — <?= e($price($t['priceUnits'])) ?></option>
                            <?php endforeach ?>
                        </select></div>
                    <div><label>Cantidad de monedas</label><input id="cx-qty" type="number" min="1" step="1" class="form-control" style="width:160px"></div>
                    <div><button type="button" class="btn btn-primary" id="cx-buy" disabled>Comprar monedas</button></div>
                </div>
                <p id="cx-quote" style="min-height:22px;margin-top:10px"></p>
            </div>
        </div>

        <div id="wl-qr" hidden>
            <div class="wl-qr">
                <img id="wl-qr-img" alt="QR de pago" hidden>
                <div>
                    <div style="font-size:26px;font-weight:700">Bs <span id="wl-qr-amount"></span></div>
                    <div id="wl-qr-lines" class="wl-note" style="margin:6px 0 2px"></div>
                    <div id="wl-qr-wallet" class="wl-note" style="margin:0 0 10px"></div>
                    <p>Escanea con tu app bancaria. Acreditamos las monedas apenas se confirme el pago.</p>
                    <p id="wl-qr-state" class="wl-note">Esperando el pago… vence en <strong id="wl-qr-left"></strong></p>
                    <a href="javascript:;" id="wl-qr-cancel" class="wl-note">Cancelar y elegir otro monto</a>
                </div>
            </div>
        </div>
    </div>

    <!-- USAR MI SALDO EN Bs -->
    <div data-pane="buy" hidden>
        <p class="wl-note" style="margin-bottom:12px">Tu saldo en Bs es dinero tuyo (el sobrante de tus recargas). Cámbialo por las monedas que quieras al <strong>precio de lista, sin comisión</strong>.</p>
        <div class="wl-banner">Saldo disponible: <strong data-wallet-inline>Bs <?= e($bs($walletUnits)) ?></strong></div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-bottom:14px">
            <div><label>Moneda</label>
                <select id="by-coin" class="form-control custom-select">
                    <?php foreach ($buyable as $t): ?>
                        <option value="<?= e($t['code']) ?>" data-price-units="<?= $t['priceUnits'] ?>"><?= e($t['label']) ?> — <?= e($price($t['priceUnits'])) ?></option>
                    <?php endforeach ?>
                </select></div>
            <div><label>Cantidad</label><input id="by-qty" type="number" min="1" step="1" class="form-control" style="width:130px"></div>
            <div><a href="javascript:;" id="by-max">máximo</a></div>
        </div>
        <p id="by-hint" class="wl-note" style="margin:-6px 0 8px"></p>
        <p id="by-quote" style="min-height:22px"></p>
        <button type="button" class="btn btn-primary" id="by-go" disabled>Comprar con mi saldo</button>
    </div>

    <!-- INTERCAMBIAR -->
    <div data-pane="exchange" hidden>
        <p class="wl-note" style="margin-bottom:12px">Cambia monedas entre sí con una comisión de <strong><?= rtrim(rtrim(number_format($feePercent, 2, '.', ''), '0'), '.') ?>%</strong> sobre lo que entregas. El oro no se intercambia. Ves al instante cuántas recibes.</p>
        <div class="form-inline" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-bottom:10px">
            <div><label>Entrego</label>
                <select id="ex-from" class="form-control custom-select">
                    <?php foreach ($types as $t): if (!$t['exchangeable']) continue; ?>
                        <option value="<?= e($t['code']) ?>" data-price-units="<?= \Aero\Credits\Classes\Money::units($t['price']) ?>" data-balance="<?= (int) $t['balance'] ?>" data-name="<?= e(str_replace('Monedas de ', '', $t['label'])) ?>"><?= e($t['label']) ?> (tienes <?= $fmt($t['balance']) ?>)</option>
                    <?php endforeach ?>
                </select></div>
            <div><label>Cantidad</label><input id="ex-amount" type="number" min="1" step="1" class="form-control" style="width:130px"></div>
            <div><a href="javascript:;" id="ex-max">máximo</a></div>
            <div><label>Recibo</label>
                <select id="ex-to" class="form-control custom-select">
                    <?php foreach ($types as $t): if (!$t['exchangeable']) continue; ?>
                        <option value="<?= e($t['code']) ?>" data-price-units="<?= \Aero\Credits\Classes\Money::units($t['price']) ?>" data-name="<?= e(str_replace('Monedas de ', '', $t['label'])) ?>"><?= e($t['label']) ?></option>
                    <?php endforeach ?>
                </select></div>
        </div>
        <p id="ex-rate" class="wl-note" style="margin-bottom:6px"></p>
        <p id="ex-quote" style="min-height:24px;font-size:15px"></p>
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
                    <?php $isMoney = (bool) ($m->creditType->is_money ?? false); ?>
                    <td style="text-align:right;white-space:nowrap" class="<?= $m->delta >= 0 ? 'wl-pos' : 'wl-neg' ?>"><?= ($m->delta > 0 ? '+' : ($m->delta < 0 ? '−' : '')) . ($isMoney ? 'Bs ' . $bs($m->delta, $m->delta < 0 ? 'ceil' : 'floor') : $fmt(abs($m->delta))) ?></td>
                    <td style="text-align:right;white-space:nowrap"><?= $isMoney ? 'Bs ' . $bs($m->balance_after) : $fmt($m->balance_after) ?></td>
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
    var bs = function (n) { return n.toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
    var amount = 0, purchaseId = null, timers = [];

    // pestañas
    $$('[data-tab]').forEach(function (b) {
        b.addEventListener('click', function () {
            $$('[data-tab]').forEach(function (x) { x.classList.toggle('on', x === b); });
            $$('[data-pane]').forEach(function (p) { p.hidden = p.getAttribute('data-pane') !== b.getAttribute('data-tab'); });
        });
    });

    // ---- recarga (cerrada: una sola moneda por recarga, el cambio va al saldo en Bs) ----
    function rows() { return $$('#wl-alloc tbody tr'); }
    // Todo en 2 decimales. Saldos/cambio hacia abajo, costos hacia arriba (las cifras mostradas siguen sumando).
    var bs2 = function (units, mode) {
        var c = mode === 'ceil' ? Math.ceil(units / 100) : (mode === 'round' ? Math.round(units / 100) : Math.floor(units / 100));
        return (c / 100).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };
    var bsLabel = function (units) { return (units > 0 && units < 100) ? 'menos de Bs 0,01' : 'Bs ' + bs2(units); };
    var priceLabel = function (pu) { return pu >= 10000 ? 'Bs ' + bs2(pu, 'round') + ' c/u' : 'Bs ' + bs2(pu * 100, 'round') + ' por 100'; };
    function recalc() {
        var total = amount * 10000;
        $$('[data-amount-label]').forEach(function (el) { el.textContent = amount.toLocaleString('es-BO'); });
        rows().forEach(function (tr) {
            var pu = parseInt(tr.getAttribute('data-price-units'), 10), btn = $('[data-buy]', tr);
            if (pu === 0) { btn.disabled = false; return; } // fila del saldo en Bs
            var coins = Math.floor(total / pu), change = total - coins * pu;
            $('[data-coins]', tr).textContent = fmt(coins);
            $('[data-change]', tr).textContent = coins < 1 ? 'no alcanza para 1' : (change > 0 ? bsLabel(change) : 'sin cambio');
            $('[data-change]', tr).className = coins < 1 ? 'wl-bad' : 'wl-note';
            btn.disabled = coins < 1;
        });
    }
    $$('[data-amount]').forEach(function (b) {
        b.addEventListener('click', function () {
            $$('[data-amount]').forEach(function (x) { x.classList.toggle('on', x === b); });
            amount = parseInt(b.getAttribute('data-amount'), 10);
            $('#wl-alloc').hidden = false;
            $('#wl-buy-error').textContent = '';
            recalc();
        });
    });

    function stopTimers() { timers.forEach(clearInterval); timers = []; }
    function showForm() { stopTimers(); purchaseId = null; $('#wl-qr').hidden = true; $('#wl-form').hidden = false; }

    function startPayment(data) {
        $$('[data-buy], #cx-buy').forEach(function (x) { x.disabled = true; });
        window.jQuery.request('onCreatePurchase', {
            data: data,
            success: function (d) {
                purchaseId = d.id;
                $('#wl-form').hidden = true; $('#wl-qr').hidden = false;
                $('#wl-qr-amount').textContent = d.amount;
                $('#wl-qr-lines').textContent = d.lines.length ? d.lines.map(function (l) { return fmt(l.coins) + ' ' + l.label.replace('Monedas de ', ''); }).join(' · ') : 'Todo el monto irá a tu saldo en Bs';
                $('#wl-qr-wallet').textContent = (d.lines.length && d.wallet_units > 0) ? '+ ' + d.wallet_bs + ' a tu saldo en Bs' : '';
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
            complete: function () { recalc(); customRecalc(); }
        });
    }
    rows().forEach(function (tr) {
        $('[data-buy]', tr).addEventListener('click', function () {
            if (!amount) return;
            startPayment({ amount: amount, coin: tr.getAttribute('data-code') });
        });
    });

    // ---- cantidad exacta de una moneda (sin cambio; el redondeo al centavo va al saldo) ----
    var MIN_AMOUNT = <?= (int) $minAmount ?>, MAX_AMOUNT = <?= (int) $maxAmount ?>;
    function customRecalc() {
        var opt = $('#cx-coin').options[$('#cx-coin').selectedIndex], pu = parseInt(opt.getAttribute('data-price-units'), 10);
        var qty = parseInt($('#cx-qty').value, 10) || 0, out = $('#cx-quote'), btn = $('#cx-buy');
        var minCoins = Math.ceil(MIN_AMOUNT * 10000 / pu);
        $('#cx-qty').placeholder = 'mínimo ' + fmt(minCoins);
        btn.disabled = true;
        if (!qty) { out.className = 'wl-note'; out.textContent = 'Mínimo con esta moneda: ' + fmt(minCoins) + ' monedas (Bs ' + MIN_AMOUNT + ').'; return; }
        var cost = qty * pu, cents = Math.ceil(cost / 100), pay = cents / 100, extra = cents * 100 - cost;
        if (pay < MIN_AMOUNT) { out.className = 'wl-bad'; out.textContent = 'El mínimo de recarga es Bs ' + MIN_AMOUNT + ': necesitas al menos ' + fmt(minCoins) + ' monedas.'; return; }
        if (pay > MAX_AMOUNT) { out.className = 'wl-bad'; out.textContent = 'El máximo por recarga es Bs ' + MAX_AMOUNT.toLocaleString('es-BO') + '. Divide tu compra en varias.'; return; }
        out.className = '';
        out.innerHTML = 'Pagarás <strong>Bs ' + pay.toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</strong> por <strong>' + fmt(qty) + '</strong> monedas'
            + (extra > 0 ? ' <span class="wl-note">· el redondeo (menos de Bs 0,01) va a tu saldo en Bs</span>' : ' <span class="wl-note">· sin cambio</span>');
        btn.disabled = false;
    }
    ['cx-coin', 'cx-qty'].forEach(function (id) { $('#' + id).addEventListener('input', customRecalc); $('#' + id).addEventListener('change', customRecalc); });
    $('#cx-buy').addEventListener('click', function () {
        var qty = parseInt($('#cx-qty').value, 10) || 0;
        if (qty < 1) return;
        startPayment({ coin: $('#cx-coin').value, coins: qty });
    });
    customRecalc();

    $('#wl-qr-cancel').addEventListener('click', function () {
        if (purchaseId) window.jQuery.request('onCancelPurchase', { data: { id: purchaseId } });
        showForm();
    });

    // ---- comprar con el saldo en Bs ----
    var walletUnits = <?= (int) $walletUnits ?>;
    function buyMax() {
        var opt = $('#by-coin').options[$('#by-coin').selectedIndex], pu = parseInt(opt.getAttribute('data-price-units'), 10);
        var max = Math.floor(walletUnits / pu), hint = $('#by-hint');
        if (max >= 1) { hint.className = 'wl-note'; hint.textContent = 'Máximo con tu saldo: ' + fmt(max) + ' monedas (Bs ' + bs2(max * pu, 'ceil') + ').'; }
        else if (walletUnits <= 0) { hint.className = 'wl-warn'; hint.textContent = 'Aún no tienes saldo en Bs. Se acumula con el cambio de tus recargas (pestaña Recargar).'; }
        else { hint.className = 'wl-warn'; hint.textContent = 'Tu saldo (Bs ' + bs2(walletUnits) + ') no alcanza para 1 moneda de este tipo (cuesta ' + priceLabel(pu) + ').'; }
        return max;
    }
    function buyQuote() {
        var opt = $('#by-coin').options[$('#by-coin').selectedIndex], pu = parseInt(opt.getAttribute('data-price-units'), 10);
        var qty = parseInt($('#by-qty').value, 10) || 0, out = $('#by-quote'), go = $('#by-go'), cost = qty * pu;
        buyMax();
        go.disabled = true;
        if (!qty) { out.textContent = ''; return; }
        if (cost > walletUnits) { out.className = 'wl-bad'; out.textContent = 'Cuestan Bs ' + bs2(cost, 'ceil') + ' y tu saldo es Bs ' + bs2(walletUnits) + '.'; return; }
        // En centavos, para que lo mostrado cuadre con el saldo mostrado: saldo(↓) − costo(↑) = te quedarán.
        var leftCents = Math.max(0, Math.floor(walletUnits / 100) - Math.ceil(cost / 100));
        out.className = ''; out.innerHTML = 'Pagarás <strong>Bs ' + bs2(cost, 'ceil') + '</strong> · te quedarán Bs ' + (leftCents / 100).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        go.disabled = false;
    }
    ['by-coin', 'by-qty'].forEach(function (id) { $('#' + id).addEventListener('input', buyQuote); $('#' + id).addEventListener('change', buyQuote); });
    $('#by-max').addEventListener('click', function () {
        var max = buyMax();
        $('#by-qty').value = max >= 1 ? max : '';
        buyQuote();
    });
    $('#by-go').addEventListener('click', function () {
        if (!confirm('¿Comprar estas monedas con tu saldo en Bs?')) return;
        this.disabled = true;
        window.jQuery.request('onBuyWithWallet', { data: { coin: $('#by-coin').value, coins: parseInt($('#by-qty').value, 10) }, success: function () { location.reload(); } });
    });
    buyMax();

    // ---- intercambio: se calcula al instante, con la misma aritmética entera del servidor ----
    var FEE_BP = <?= (int) round($feePercent * 100) ?>;
    function exSel(id) { var el = $('#' + id); return el.options[el.selectedIndex]; }
    function exFeeFor(amt) { return Math.floor((amt * FEE_BP + 9999) / 10000); } // ceil(amt × comisión)
    function exReceive(amt, pf, pt) { return Math.floor((amt - exFeeFor(amt)) * pf / pt); }
    var exCanGo = false;
    function exQuote() {
        var from = $('#ex-from'), to = $('#ex-to');
        // La moneda de salida no puede ser también la de llegada.
        $$('option', to).forEach(function (o) { var same = o.value === from.value; o.disabled = same; o.hidden = same; });
        if (to.value === from.value) { var alt = $$('option', to).filter(function (o) { return o.value !== from.value; })[0]; if (alt) to.value = alt.value; }
        var fo = exSel('ex-from'), tOpt = exSel('ex-to'), out = $('#ex-quote'), go = $('#ex-go'), rate = $('#ex-rate');
        var pf = parseInt(fo.getAttribute('data-price-units'), 10), pt = parseInt(tOpt.getAttribute('data-price-units'), 10);
        var bal = parseInt(fo.getAttribute('data-balance'), 10), fn = fo.getAttribute('data-name'), tn = tOpt.getAttribute('data-name');
        var amt = parseInt($('#ex-amount').value, 10) || 0;
        go.disabled = true; exCanGo = false;
        rate.textContent = 'Precio: 1 moneda de ' + tn + ' = ' + (pt / pf).toLocaleString('es-BO', { maximumFractionDigits: 2 }) + ' monedas de ' + fn + ' (antes de la comisión).';
        if (!amt) { out.className = 'wl-note'; out.textContent = 'Escribe cuántas monedas de ' + fn + ' quieres entregar (tienes ' + fmt(bal) + ').'; return; }
        if (amt > bal) { out.className = 'wl-bad'; out.textContent = 'Solo tienes ' + fmt(bal) + ' monedas de ' + fn + '.'; return; }
        var fee = exFeeFor(amt), got = exReceive(amt, pf, pt);
        if (got < 1) {
            var need = 1; while (exReceive(need, pf, pt) < 1) { need++; }
            out.className = 'wl-bad'; out.textContent = 'Muy poco: no alcanza para 1 moneda de ' + tn + '. Entrega al menos ' + fmt(need) + ' monedas de ' + fn + '.'; return;
        }
        out.className = '';
        out.innerHTML = 'Entregas <strong>' + fmt(amt) + '</strong> monedas de ' + fn + ' · comisión <strong>' + fmt(fee) + '</strong> · recibes <strong style="font-size:18px">' + fmt(got) + '</strong> monedas de ' + tn;
        exCanGo = true; go.disabled = false;
    }
    ['ex-from', 'ex-to', 'ex-amount'].forEach(function (id) { $('#' + id).addEventListener('input', exQuote); $('#' + id).addEventListener('change', exQuote); });
    $('#ex-max').addEventListener('click', function () { $('#ex-amount').value = parseInt(exSel('ex-from').getAttribute('data-balance'), 10) || ''; exQuote(); });
    $('#ex-go').addEventListener('click', function () {
        if (!exCanGo || !confirm('¿Confirmar el intercambio? No se puede deshacer.')) return;
        this.disabled = true;
        window.jQuery.request('onExchange', { data: { from: $('#ex-from').value, to: $('#ex-to').value, amount: parseInt($('#ex-amount').value, 10) }, success: function () { location.reload(); } });
    });
    exQuote();
})();
</script>
<?php endif ?>
</div>
