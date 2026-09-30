<?php
$fmt = fn ($n) => number_format((float) $n, 0, ',', '.');
?>
<style>
.ms{
    --ms-surface: color-mix(in srgb, var(--bs-body-color) 5%, var(--bs-body-bg));
    --ms-hover: color-mix(in srgb, var(--bs-body-color) 9%, var(--bs-body-bg));
    --ms-line: var(--bs-border-color);
    --ms-muted: var(--bs-secondary-color);
    --ms-accent: var(--oc-accent, #3498db);
    --ms-ok: color-mix(in srgb, var(--bs-success, #41b862) 85%, var(--bs-body-color));
    --ms-bad: color-mix(in srgb, var(--bs-danger, #dc3545) 85%, var(--bs-body-color));
    max-width:1080px;color:var(--bs-body-color)
}
.ms-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:24px}
.ms-card{border:1px solid var(--ms-line);border-radius:8px;padding:12px 14px;background:var(--ms-surface)}
.ms-card .lbl{display:flex;align-items:center;gap:7px;font-size:12px;color:var(--ms-muted)}
.ms-dot{width:10px;height:10px;border-radius:50%;display:inline-block;flex:0 0 auto}
.ms-card .num{font-size:22px;font-weight:700;margin-top:4px}
.ms-tabs{display:flex;gap:4px;border-bottom:1px solid var(--ms-line);margin-bottom:18px}
.ms-tabs button{border:0;background:none;padding:9px 16px;font-weight:600;color:var(--ms-muted);border-bottom:2px solid transparent;cursor:pointer}
.ms-tabs button.on{color:var(--bs-emphasis-color,var(--bs-body-color));border-color:var(--ms-accent)}
.ms-services{display:flex;flex-direction:column;gap:18px}
.ms-service{border:1px solid var(--ms-line);border-radius:10px;padding:16px 18px;background:var(--ms-surface)}
.ms-service h4{margin:0 0 2px;font-weight:700}
.ms-service .cat{font-size:11px;color:var(--ms-muted);text-transform:uppercase}
.ms-service .summary{font-size:13px;color:var(--ms-muted);margin:6px 0 12px}
.ms-plans{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px}
.ms-plan{border:1px solid var(--ms-line);border-radius:8px;padding:12px;background:var(--bs-body-bg)}
.ms-plan .name{font-weight:600;margin-bottom:4px}
.ms-plan .price{font-size:13px;margin:2px 0}
.ms-plan .price.primary{font-weight:700;font-size:15px}
.ms-plan .sub{font-size:11px;color:var(--ms-muted)}
.ms-plan .btns{display:flex;gap:6px;flex-wrap:wrap;margin-top:10px}
.ms-table{width:100%;border-collapse:collapse}
.ms-table th{font-size:11px;text-transform:uppercase;color:var(--ms-muted);text-align:left;padding:6px 8px;border-bottom:1px solid var(--ms-line)}
.ms-table td{padding:9px 8px;border-bottom:1px solid var(--ms-line);vertical-align:middle}
.ms-note{font-size:12px;color:var(--ms-muted)}
.ms-badge{display:inline-block;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:600}
.ms-badge.pending{background:color-mix(in srgb,#f59e0b 20%,transparent);color:#f59e0b}
.ms-badge.fulfilled{background:color-mix(in srgb,var(--ms-ok) 20%,transparent);color:var(--ms-ok)}
.ms-badge.cancelled{background:color-mix(in srgb,var(--ms-bad) 20%,transparent);color:var(--ms-bad)}
[hidden]{display:none!important}
</style>

<div class="ms">
<?php if (!$tenantId): ?>
    <div class="callout callout-info"><div class="header"><h3>Elige un sitio</h3><p>Selecciona un sitio (tenant) para ver y comprar servicios.</p></div></div>
<?php else: ?>

    <div class="ms-cards">
        <?php foreach ($balances as $code => $b): ?>
            <div class="ms-card">
                <div class="lbl"><span class="ms-dot" style="background:<?= e($b['color']) ?>"></span><?= e($b['label']) ?></div>
                <div class="num"><?= $fmt($b['balance']) ?></div>
            </div>
        <?php endforeach ?>
        <div class="ms-card">
            <div class="lbl"><span class="ms-dot" style="background:#22c55e"></span>Saldo en Bs</div>
            <div class="num">Bs <?= e($walletBs) ?></div>
        </div>
    </div>
    <p class="ms-note" style="margin:-14px 0 20px">¿Te falta saldo? Recárgalo desde <a href="<?= Backend::url('aero/credits/wallet') ?>">Wallet</a>.</p>

    <div class="ms-tabs">
        <button type="button" class="on" data-tab="catalog">Catálogo</button>
        <button type="button" data-tab="history">Mis compras</button>
    </div>

    <!-- CATÁLOGO -->
    <div data-pane="catalog">
        <?php if ($services->isEmpty()): ?>
            <p class="ms-note">Todavía no hay servicios disponibles para comprar.</p>
        <?php endif ?>
        <div class="ms-services">
        <?php foreach ($services as $service): ?>
            <div class="ms-service">
                <?php if ($service->category): ?><div class="cat"><?= e($service->category->name) ?></div><?php endif ?>
                <h4><?= e($service->name) ?></h4>
                <?php if ($service->summary): ?><div class="summary"><?= e($service->summary) ?></div><?php endif ?>
                <div class="ms-plans">
                <?php foreach ((array) $service->plans as $i => $plan): ?>
                    <div class="ms-plan">
                        <div class="name"><?= e($plan['name'] ?? 'Plan') ?></div>
                        <?php foreach ($service->planPriceLines($plan) as $line): ?>
                            <div class="price<?= $line['primary'] ? ' primary' : '' ?>"><?= e($line['text']) ?></div>
                        <?php endforeach ?>
                        <?php if (!empty($plan['delivery_days'])): ?><div class="sub">Entrega en <?= (int) $plan['delivery_days'] ?> días</div><?php endif ?>
                        <div class="btns">
                            <?php if (($plan['type'] ?? null) === 'free'): ?>
                                <button type="button" class="btn btn-primary btn-sm" data-buy
                                    data-service="<?= $service->id ?>" data-plan="<?= $i ?>" data-method="free"
                                    data-confirm="¿Solicitar «<?= e($plan['name'] ?? '') ?>»? Es gratis.">Solicitar gratis</button>
                            <?php elseif (in_array($plan['pricing_mode'] ?? 'money', ['credits', 'both'], true) && filled($plan['credit_price'] ?? null)): ?>
                                <button type="button" class="btn btn-primary btn-sm" data-buy
                                    data-service="<?= $service->id ?>" data-plan="<?= $i ?>" data-method="credits"
                                    data-confirm="¿Comprar «<?= e($plan['name'] ?? '') ?>» con tus créditos?">Comprar con créditos</button>
                            <?php endif ?>
                            <?php if (($plan['type'] ?? null) !== 'free' && in_array($plan['pricing_mode'] ?? 'money', ['money', 'both'], true) && filled($plan['price'] ?? null)): ?>
                                <button type="button" class="btn btn-default btn-sm" data-buy
                                    data-service="<?= $service->id ?>" data-plan="<?= $i ?>" data-method="money"
                                    data-confirm="¿Comprar «<?= e($plan['name'] ?? '') ?>» con tu saldo en Bs?">Comprar con mi saldo en Bs</button>
                            <?php endif ?>
                            <?php if (($plan['type'] ?? null) !== 'free' && ($plan['pricing_mode'] ?? 'money') === 'money' && empty($plan['price'])): ?>
                                <span class="ms-note">A cotizar: escríbenos.</span>
                            <?php endif ?>
                        </div>
                    </div>
                <?php endforeach ?>
                </div>
            </div>
        <?php endforeach ?>
        </div>
    </div>

    <!-- MIS COMPRAS -->
    <div data-pane="history" hidden>
        <table class="ms-table">
            <thead><tr><th>Fecha</th><th>Servicio</th><th>Plan</th><th>Pagado</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($purchases as $p): ?>
                <tr>
                    <td class="ms-note"><?= e($p->created_at?->format('d/m/Y H:i')) ?></td>
                    <td><?= e($p->service->name ?? '—') ?></td>
                    <td><?= e($p->plan_name) ?></td>
                    <td><?= e($p->amount_label) ?></td>
                    <td><span class="ms-badge <?= e($p->status) ?>"><?= e($p->status_label) ?></span></td>
                    <td><?php if ($p->status === 'pending'): ?>
                        <a href="javascript:;" data-cancel="<?= $p->id ?>" data-confirm="¿Cancelar esta compra? Se te reembolsa de inmediato.">Cancelar</a>
                    <?php endif ?></td>
                </tr>
            <?php endforeach ?>
            <?php if ($purchases->isEmpty()): ?><tr><td colspan="6" class="ms-note">Aún no compraste ningún servicio.</td></tr><?php endif ?>
            </tbody>
        </table>
    </div>

<script>
(function () {
    var root = document.querySelector('.ms');
    if (!root) return;
    var $$ = function (s, c) { return Array.prototype.slice.call((c || root).querySelectorAll(s)); };

    $$('[data-tab]').forEach(function (b) {
        b.addEventListener('click', function () {
            $$('[data-tab]').forEach(function (x) { x.classList.toggle('on', x === b); });
            $$('[data-pane]').forEach(function (p) { p.hidden = p.getAttribute('data-pane') !== b.getAttribute('data-tab'); });
        });
    });

    $$('[data-buy]').forEach(function (b) {
        b.addEventListener('click', function () {
            if (!confirm(b.getAttribute('data-confirm'))) return;
            b.disabled = true;
            window.jQuery.request('onBuy', {
                data: { service_id: b.getAttribute('data-service'), plan_index: b.getAttribute('data-plan'), method: b.getAttribute('data-method') },
                success: function () { location.reload(); },
                complete: function () { b.disabled = false; }
            });
        });
    });

    $$('[data-cancel]').forEach(function (a) {
        a.addEventListener('click', function () {
            if (!confirm(a.getAttribute('data-confirm'))) return;
            window.jQuery.request('onCancel', { data: { id: a.getAttribute('data-cancel') }, success: function () { location.reload(); } });
        });
    });
})();
</script>
<?php endif ?>
</div>
