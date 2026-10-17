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
    width:100%;color:var(--bs-body-color)
}
.ms-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:24px}
.ms-card{border:1px solid var(--ms-line);border-radius:8px;padding:12px 14px;background:var(--ms-surface)}
.ms-card .lbl{display:flex;align-items:center;gap:7px;font-size:12px;color:var(--ms-muted)}
.ms-dot{width:10px;height:10px;border-radius:50%;display:inline-block;flex:0 0 auto}
.ms-card .num{font-size:22px;font-weight:700;margin-top:4px}
.ms-tabs{display:flex;gap:4px;border-bottom:1px solid var(--ms-line);margin-bottom:18px}
.ms-tabs button{border:0;background:none;padding:9px 16px;font-weight:600;color:var(--ms-muted);border-bottom:2px solid transparent;cursor:pointer}
.ms-tabs button.on{color:var(--bs-emphasis-color,var(--bs-body-color));border-color:var(--ms-accent)}
.ms-services{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;align-items:start}
.ms-service{position:relative;overflow:hidden;display:flex;flex-direction:column;border:1px solid var(--ms-line);border-radius:14px;padding:18px;background:linear-gradient(160deg,color-mix(in srgb,var(--ms-accent) 9%,var(--ms-surface)),var(--ms-surface) 55%);transition:transform .15s,box-shadow .15s,border-color .15s}
.ms-service:hover{transform:translateY(-2px);border-color:color-mix(in srgb,var(--ms-accent) 55%,var(--ms-line));box-shadow:0 8px 24px color-mix(in srgb,var(--ms-accent) 16%,transparent)}
.ms-head{display:flex;align-items:center;gap:12px;margin-bottom:10px}
.ms-ico{flex:0 0 auto;width:46px;height:46px;border-radius:12px;display:grid;place-items:center;color:#fff;background:linear-gradient(135deg,var(--ms-accent),color-mix(in srgb,var(--ms-accent) 55%,#8b5cf6));box-shadow:0 4px 12px color-mix(in srgb,var(--ms-accent) 35%,transparent)}
.ms-ico svg{width:24px;height:24px}
.ms-service h4{margin:0;font-weight:700;font-size:16px;line-height:1.2}
.ms-service .cat{display:inline-block;margin-top:3px;padding:1px 8px;border-radius:99px;font-size:10px;font-weight:600;letter-spacing:.03em;color:var(--ms-accent);background:color-mix(in srgb,var(--ms-accent) 14%,transparent);text-transform:uppercase}
.ms-service .summary{font-size:13px;line-height:1.45;color:var(--ms-muted);margin:0 0 14px}
.ms-plans{display:flex;flex-direction:column;gap:10px;margin-top:auto}
.ms-plan{border:1px solid var(--ms-line);border-radius:10px;padding:12px;background:var(--bs-body-bg)}
.ms-plan .name{font-weight:600;margin-bottom:4px}
.ms-plan .price{font-size:13px;margin:2px 0;color:var(--ms-muted)}
.ms-plan .price.primary{font-weight:700;font-size:18px;color:var(--bs-emphasis-color,var(--bs-body-color))}
.ms-plan .price.save{display:inline-block;font-size:11px;font-weight:600;padding:1px 8px;border-radius:99px;color:var(--ms-ok);background:color-mix(in srgb,var(--ms-ok) 16%,transparent)}
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
.ms-bar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:14px}
.ms-search{position:relative;flex:1 1 260px;max-width:420px}
.ms-search input{width:100%;padding:8px 12px 8px 34px;border:1px solid var(--ms-line);border-radius:99px;background:var(--bs-body-bg);color:var(--bs-body-color)}
.ms-search input:focus{outline:2px solid color-mix(in srgb,var(--ms-accent) 45%,transparent);border-color:var(--ms-accent)}
.ms-search svg{position:absolute;left:11px;top:50%;transform:translateY(-50%);width:16px;height:16px;color:var(--ms-muted)}
.ms-chips{display:flex;flex-wrap:wrap;gap:6px}
.ms-chip{border:1px solid var(--ms-line);background:var(--ms-surface);color:var(--ms-muted);border-radius:99px;padding:4px 12px;font-size:12px;font-weight:600;cursor:pointer}
.ms-chip:hover{background:var(--ms-hover)}
.ms-chip.on{color:#fff;background:var(--ms-accent);border-color:var(--ms-accent)}
.ms-count{font-size:12px;color:var(--ms-muted);margin-left:auto}
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
        <?php
            $cats = $services->map(fn ($x) => $x->category?->name)->filter()->unique()->sort()->values();
            $mk = function ($x) {
                $modes = collect((array) $x->plans)->map(function ($p) {
                    if (($p['type'] ?? null) === 'free') return ['free'];
                    return match ($p['pricing_mode'] ?? 'money') { 'credits' => ['credits'], 'both' => ['credits', 'money'], default => ['money'] };
                })->flatten()->unique()->implode(' ');
                $saves = collect((array) $x->plans)->contains(fn ($p) => ($p['pricing_mode'] ?? '') === 'both' && $x->planCurrencySaving($p));
                return trim($modes . ($saves ? ' saving' : ''));
            };
        ?>
        <div class="ms-bar">
            <label class="ms-search">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="search" id="ms-q" placeholder="Buscar servicio…" autocomplete="off">
            </label>
            <div class="ms-chips" data-filter="pay">
                <button type="button" class="ms-chip on" data-v="">Todos</button>
                <button type="button" class="ms-chip" data-v="credits">Con créditos</button>
                <button type="button" class="ms-chip" data-v="money">Con saldo Bs</button>
                <button type="button" class="ms-chip" data-v="free">Gratis</button>
                <button type="button" class="ms-chip" data-v="saving">Con ahorro</button>
            </div>
            <span class="ms-count" id="ms-count"></span>
        </div>
        <?php if ($cats->count() > 1): ?>
        <div class="ms-chips" data-filter="cat" style="margin-bottom:16px">
            <button type="button" class="ms-chip on" data-v="">Todas las categorías</button>
            <?php foreach ($cats as $c): ?><button type="button" class="ms-chip" data-v="<?= e(mb_strtolower($c)) ?>"><?= e($c) ?></button><?php endforeach ?>
        </div>
        <?php endif ?>
        <p class="ms-note" id="ms-empty" hidden>Ningún servicio coincide con tu búsqueda.</p>
        <div class="ms-services">
        <?php foreach ($services as $service): ?>
            <div class="ms-service" data-name="<?= e(mb_strtolower($service->name . ' ' . $service->summary)) ?>" data-cat="<?= e(mb_strtolower((string) $service->category?->name)) ?>" data-pay="<?= e($mk($service)) ?>">
                <div class="ms-head">
                    <span class="ms-ico"><?= \Aero\Services\Classes\LucideIcon::svg($service->icon, '') ?: \Aero\Services\Classes\LucideIcon::svg('package', '') ?></span>
                    <div>
                        <h4><?= e($service->name) ?></h4>
                        <?php if ($service->category): ?><span class="cat"><?= e($service->category->name) ?></span><?php endif ?>
                    </div>
                </div>
                <?php if ($service->summary): ?><p class="summary"><?= e($service->summary) ?></p><?php endif ?>
                <div class="ms-plans">
                <?php foreach ((array) $service->plans as $i => $plan): ?>
                    <div class="ms-plan">
                        <div class="name"><?= e($plan['name'] ?? 'Plan') ?></div>
                        <?php foreach ($service->planPriceLines($plan) as $line): ?>
                            <div class="price<?= $line['primary'] ? ' primary' : '' ?><?= str_starts_with($line['text'], 'Ahorras') ? ' save' : '' ?>"><?= e($line['text']) ?></div>
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

    (function () {
        var cards = $$('.ms-service'), q = document.getElementById('ms-q'), st = {pay: '', cat: ''};
        var norm = function (t) { return (t || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, ''); };
        cards.forEach(function (c) { c._n = norm(c.getAttribute('data-name')); });
        function apply() {
            var terms = norm(q && q.value).split(/\s+/).filter(Boolean), n = 0;
            cards.forEach(function (c) {
                var ok = terms.every(function (t) { return c._n.indexOf(t) > -1; })
                    && (!st.pay || (' ' + c.getAttribute('data-pay') + ' ').indexOf(' ' + st.pay + ' ') > -1)
                    && (!st.cat || c.getAttribute('data-cat') === st.cat);
                c.hidden = !ok; if (ok) n++;
            });
            var e = document.getElementById('ms-empty'), k = document.getElementById('ms-count');
            if (e) e.hidden = n > 0 || !cards.length;
            if (k) k.textContent = n + ' de ' + cards.length;
        }
        if (q) q.addEventListener('input', apply);
        $$('[data-filter]').forEach(function (g) {
            g.addEventListener('click', function (ev) {
                var b = ev.target.closest('.ms-chip'); if (!b) return;
                $$('.ms-chip', g).forEach(function (x) { x.classList.toggle('on', x === b); });
                st[g.getAttribute('data-filter')] = b.getAttribute('data-v'); apply();
            });
        });
        apply();
    })();

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
