<div class="aero-credits-widget">
    <?php if (!$tenantId): ?>
        <p class="text-muted">No hay un tenant seleccionado.</p>
    <?php elseif (!$types): ?>
        <p class="text-muted">No hay tipos de crédito configurados todavía.</p>
    <?php else: ?>
        <div style="display:flex;flex-wrap:wrap;gap:8px">
            <?php foreach ($types as $type): ?>
                <div style="flex:1 1 150px;display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 12px;background:<?= e($type['color']) ?>1f;border-left:3px solid <?= e($type['color']) ?>;border-radius:6px">
                    <strong style="font-size:12px"><?= e($type['label']) ?></strong>
                    <div style="text-align:right">
                        <div style="font-size:18px;font-weight:600;line-height:1.1"><?= number_format($type['balance']) ?></div>
                        <div class="text-muted" style="font-size:11px">≈ Bs <?= e($type['bob_value']) ?></div>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
        <div style="margin-top:4px;font-size:11px"><a href="<?= Backend::url('aero/credits/wallet') ?>">Comprar créditos en la billetera →</a></div>
    <?php endif ?>
</div>
