<div class="sign">
    <div>
        <?php if ($signature): ?><img src="<?= $signature ?>" alt="Firma"><?php endif ?>
        <div class="line"><strong><?= e($c->professional_name) ?></strong><br>
            <?= e($profile?->specialty) ?><?= $profile?->license ? ' · Mat. ' . e($profile->license) : '' ?></div>
    </div>
    <?php if ($seal): ?><div><img src="<?= $seal ?>" alt="Sello"></div><?php endif ?>
</div>
<p class="small" style="margin-top:22px">
    Firmado en el sistema el <?= e($c->signed_at->format('d/m/Y H:i')) ?> · Código de integridad de la nota <?= e($c->short_hash) ?>
    <?php if (!$integrity): ?><span class="warn"> · ATENCIÓN: el contenido no coincide con lo firmado</span><?php endif ?>
</p>
</body></html>
