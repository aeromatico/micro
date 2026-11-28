<?php
$selected = null;
foreach ($options as $k => $o) {
    if ($o['plan_index'] === $wantedPlan && ($wantedMethod === '' || $o['method'] === $wantedMethod)) { $selected = $k; break; }
}
if ($selected === null) {
    foreach ($options as $k => $o) { if (!$o['missing']) { $selected = $k; break; } }
}
$selected ??= 0;
$methodNames = ['credits' => 'con monedas', 'money' => 'con saldo en Bs', 'free' => 'sin costo'];
?>
<style>
.mb{--mb-line:var(--bs-border-color);--mb-muted:var(--bs-secondary-color);--mb-accent:var(--oc-accent,#3498db);--mb-surface:color-mix(in srgb,var(--bs-body-color) 5%,var(--bs-body-bg));max-width:640px;margin:0 auto;color:var(--bs-body-color)}
.mb-back{display:inline-block;margin-bottom:14px;font-size:13px}
.mb-box{border:1px solid var(--mb-line);border-radius:14px;padding:22px;background:var(--mb-surface)}
.mb-head{text-align:center;margin-bottom:18px}
.mb-ico{width:64px;height:64px;margin:0 auto 12px;border-radius:16px;display:grid;place-items:center;border:1px solid var(--mb-line);background:var(--bs-body-bg);color:var(--mb-accent)}
.mb-ico svg{width:32px;height:32px}
.mb-head h3{margin:0 0 6px;font-weight:700}
.mb-muted{color:var(--mb-muted);font-size:13px}
.mb-opt{display:flex;flex-wrap:wrap;gap:6px 12px;align-items:center;border:1px solid var(--mb-line);border-radius:10px;padding:12px 14px;margin-bottom:10px;background:var(--bs-body-bg);cursor:pointer;font-weight:400}
.mb-opt:has(input:checked){border-color:var(--mb-accent);box-shadow:0 0 0 1px var(--mb-accent)}
.mb-opt input{margin:0;flex:0 0 auto}
.mb-opt .t{flex:1 1 140px}
.mb-opt .t strong{display:block}
.mb-opt .c{font-weight:700;white-space:nowrap;margin-left:auto}
.mb-sum{border-top:1px solid var(--mb-line);margin-top:16px;padding-top:14px}
.mb-row{display:flex;justify-content:space-between;gap:12px;padding:3px 0}
.mb-warn{margin:10px 0 0;color:#d97706;font-weight:600}
.mb-actions{margin-top:16px;display:grid;gap:8px}
.mb [hidden]{display:none!important}
</style>

<div class="mb">
    <a class="mb-back" href="<?= Backend::url('aero/services/myservices') ?>">← Ver todo el catálogo</a>

    <div class="mb-box">
        <div class="mb-head">
            <div class="mb-ico"><?= \Aero\Services\Classes\LucideIcon::svg($service->icon, '') ?: \Aero\Services\Classes\LucideIcon::svg('puzzle', '') ?></div>
            <h3><?= e($service->name) ?></h3>
            <?php if ($service->summary): ?><div class="mb-muted"><?= e($service->summary) ?></div><?php endif ?>
        </div>

    <?php if (!$tenantId): ?>
        <div class="alert alert-info">Elige primero un sitio (tenant) para comprar.</div>
    <?php elseif (!$options): ?>
        <div class="alert alert-info">Este plugin se vende a cotización. <a href="<?= e(url('contacto')) ?>">Escríbenos</a> y armamos tu precio.</div>
    <?php else: ?>
        <div data-checkout>
            <?php if ($previous): ?>
                <div class="alert alert-warning">Ya compraste este plugin (<?= e($previous->plan_name) ?>, <?= e(mb_strtolower($previous->status_label)) ?>, <?= e($previous->created_at?->format('d/m/Y')) ?>). Si confirmas, se cobra otra vez.</div>
            <?php endif ?>

            <?php foreach ($options as $k => $o): ?>
                <label class="mb-opt">
                    <input type="radio" name="mb_option" value="<?= $k ?>" <?= $k === $selected ? 'checked' : '' ?>
                        data-plan="<?= $o['plan_index'] ?>" data-method="<?= e($o['method']) ?>">
                    <span class="t">
                        <strong><?= e($o['plan']['name'] ?? 'Plan') ?></strong>
                        <span class="mb-muted">Pagar <?= e($methodNames[$o['method']] ?? '') ?><?php
                            if ((float) ($o['plan'][$o['method'] === 'money' ? 'setup_fee' : 'credit_setup_fee'] ?? 0) > 0 && $o['method'] !== 'free') echo ' · primer pago, incluye implementación';
                            elseif (($o['plan']['type'] ?? null) === 'recurring') echo ' · ' . e(['monthly' => 'por mes', 'quarterly' => 'por trimestre', 'yearly' => 'por año'][$o['plan']['billing_period'] ?? 'monthly'] ?? '');
                            if (!empty($o['plan']['delivery_days'])) echo ' · entrega en ' . (int) $o['plan']['delivery_days'] . ' días';
                        ?></span>
                    </span>
                    <span class="c"><?= e($o['cost']) ?></span>
                </label>
            <?php endforeach ?>

            <?php foreach ($options as $k => $o): ?>
                <div class="mb-sum" data-summary="<?= $k ?>" <?= $k === $selected ? '' : 'hidden' ?>>
                    <div class="mb-row"><span>Total a pagar</span><strong><?= e($o['cost']) ?></strong></div>
                    <?php if ($o['balance'] !== null): ?><div class="mb-row mb-muted"><span>Tu saldo</span><span><?= e($o['balance']) ?></span></div><?php endif ?>
                    <?php if ($o['after'] !== null): ?><div class="mb-row mb-muted"><span>Te quedará</span><span><?= e($o['after']) ?></span></div><?php endif ?>
                    <?php if ($o['missing']): ?><p class="mb-warn">Te faltan <?= e($o['missing']) ?>.</p><?php endif ?>
                    <div class="mb-actions">
                        <?php if ($o['missing']): ?>
                            <a class="btn btn-primary btn-lg" href="<?= Backend::url('aero/credits/wallet') ?>" target="_blank" rel="noopener" data-recharge>Recargar saldo</a>
                            <span class="mb-muted text-center">Se abre en otra pestaña. Al volver, tu saldo se actualiza aquí.</span>
                        <?php else: ?>
                            <button type="button" class="btn btn-primary btn-lg" data-confirm-buy><?= $o['method'] === 'free' ? 'Obtener gratis' : 'Confirmar compra' ?></button>
                        <?php endif ?>
                    </div>
                </div>
            <?php endforeach ?>
        </div>

        <div class="text-center" data-done hidden>
            <h4>¡Listo! Tu compra quedó registrada.</h4>
            <p class="mb-muted" data-done-text></p>
            <div class="mb-actions">
                <a class="btn btn-primary btn-lg" href="<?= Backend::url('aero/services/myservices') ?>">Ir a mis compras</a>
            </div>
        </div>
    <?php endif ?>
    </div>
</div>

<script>
(function () {
    var root = document.querySelector('.mb');
    if (!root) return;
    var $$ = function (s) { return Array.prototype.slice.call(root.querySelectorAll(s)); };
    var recharging = false;

    $$('input[name=mb_option]').forEach(function (r) {
        r.addEventListener('change', function () {
            $$('[data-summary]').forEach(function (s) { s.hidden = s.getAttribute('data-summary') !== r.value; });
            var u = new URL(location.href);
            u.searchParams.set('plan', r.dataset.plan);
            u.searchParams.set('method', r.dataset.method);
            history.replaceState(history.state, '', u);
        });
    });

    $$('[data-recharge]').forEach(function (a) { a.addEventListener('click', function () { recharging = true; }); });
    document.addEventListener('visibilitychange', function () {
        if (recharging && !document.hidden && document.body.contains(root)) location.reload();
    });

    $$('[data-confirm-buy]').forEach(function (b) {
        b.addEventListener('click', function () {
            var r = root.querySelector('input[name=mb_option]:checked');
            b.disabled = true;
            window.jQuery.request('onBuy', {
                data: { service_id: <?= (int) $service->id ?>, plan_index: r.dataset.plan, method: r.dataset.method },
                success: function (data) {
                    root.querySelector('[data-checkout]').hidden = true;
                    root.querySelector('[data-done]').hidden = false;
                    root.querySelector('[data-done-text]').textContent = data && data.delivered
                        ? 'El plugin ya está activo en tu cuenta.'
                        : 'Nuestro equipo la va a entregar en breve.';
                },
                complete: function () { b.disabled = false; }
            });
        });
    });
})();
</script>
