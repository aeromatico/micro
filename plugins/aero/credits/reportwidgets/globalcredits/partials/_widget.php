<div class="aero-credits-widget">
    <?php if (!$types): ?>
        <p class="text-muted">No hay tipos de crédito configurados todavía.</p>
    <?php else: ?>
        <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:12px">
            <?php foreach ($types as $type): ?>
                <div style="flex:1;min-width:140px;padding:10px;border:1px solid #eee;border-radius:6px">
                    <div style="display:flex;align-items:center;gap:6px">
                        <span style="width:10px;height:10px;border-radius:50%;background:<?= e($type['color']) ?>;display:inline-block"></span>
                        <span class="text-muted"><?= e($type['label']) ?> — este mes</span>
                    </div>
                    <div style="font-size:20px;font-weight:600"><?= number_format($type['consumed']) ?></div>
                    <div class="text-muted" style="font-size:11px">≈ $<?= number_format($type['usd_value'], 2) ?> USD</div>
                </div>
            <?php endforeach ?>
        </div>

        <?php if ($topTenants->isNotEmpty()): ?>
            <strong class="text-muted" style="font-size:11px;text-transform:uppercase">Top tenants del mes</strong>
            <ol style="margin:6px 0 0;padding-left:18px">
                <?php foreach ($topTenants as $row): ?>
                    <li><?= e($row['name']) ?> — <?= number_format($row['total']) ?></li>
                <?php endforeach ?>
            </ol>
        <?php endif ?>
    <?php endif ?>
</div>
