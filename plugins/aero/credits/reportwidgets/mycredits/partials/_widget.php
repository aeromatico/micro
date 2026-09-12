<div class="aero-credits-widget">
    <?php if (!$tenantId): ?>
        <p class="text-muted">No hay un tenant seleccionado.</p>
    <?php elseif (!$types): ?>
        <p class="text-muted">No hay tipos de crédito configurados todavía.</p>
    <?php else: ?>
        <?php foreach ($types as $type): ?>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid #eee">
                <div style="display:flex;align-items:center;gap:8px">
                    <span style="width:12px;height:12px;border-radius:50%;background:<?= e($type['color']) ?>;display:inline-block"></span>
                    <strong><?= e($type['label']) ?></strong>
                </div>
                <div style="text-align:right">
                    <div style="font-size:18px;font-weight:600"><?= number_format($type['balance']) ?></div>
                    <div class="text-muted" style="font-size:11px">≈ $<?= number_format($type['usd_value'], 4) ?> USD</div>
                </div>
            </div>
        <?php endforeach ?>
    <?php endif ?>
</div>
