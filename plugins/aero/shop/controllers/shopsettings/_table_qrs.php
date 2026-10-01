<?php if (!$qrs): ?>
    <p class="text-muted">No hay mesas configuradas o falta el dominio principal del tenant.</p>
<?php else: ?>
    <button type="button" class="btn btn-default" onclick="window.print()"><i class="icon-print"></i> Imprimir</button>
    <div style="display:flex;flex-wrap:wrap;gap:16px;margin-top:12px">
        <?php foreach ($qrs as $n => $qr): ?>
            <div style="width:200px;text-align:center;border:1px solid #ddd;border-radius:8px;padding:12px;break-inside:avoid">
                <div style="font-size:18px;font-weight:700">Mesa <?= $n ?></div>
                <div><?= $qr['svg'] ?></div>
                <div style="font-size:11px;color:#666;word-break:break-all"><?= e($qr['url']) ?></div>
            </div>
        <?php endforeach ?>
    </div>
<?php endif ?>
