<div class="callout callout-info no-subheader" style="max-width:720px;margin:2rem auto;padding:2rem;text-align:center">
    <h2 style="margin-top:0">Esta función es parte del plan PRO</h2>
    <?php if ($feature): ?>
        <p style="font-size:1.1em"><strong><?= $feature ?></strong> está disponible para cuentas PRO.</p>
    <?php endif ?>
    <?php if ($plan): ?>
        <p>Con <strong><?= e($plan['label']) ?></strong> (Bs <?= e($plan['price']) ?>/mes) obtenés:</p>
        <ul style="display:inline-block;text-align:left;margin:0 0 1.5rem">
            <?php foreach ($plan['features'] as $f): ?>
                <li><?= e($f) ?></li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>
    <p>Contactá a soporte para activar tu plan PRO sin perder nada de lo que ya configuraste.</p>
    <p style="margin-top:1rem"><a href="<?= Backend::url('backend') ?>">Volver al inicio</a></p>
</div>
