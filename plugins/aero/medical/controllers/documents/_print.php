<?php use Aero\Medical\Classes\Mode; ?><!doctype html>
<html lang="es"><head><meta charset="utf-8"><title><?= e($d->title) ?> — <?= e($patient->name) ?></title>
<style>
    body{font-family:Arial,Helvetica,sans-serif;color:#111;margin:40px 56px;font-size:14px;line-height:1.6}
    h1{font-size:20px;margin:0}.head{border-bottom:2px solid #111;padding-bottom:8px;margin-bottom:28px;display:flex;justify-content:space-between}
    h2{text-align:center;text-transform:uppercase;letter-spacing:.06em;font-size:16px;margin:0 0 22px}
    .body{white-space:pre-wrap;text-align:justify}.sign{margin-top:70px;display:flex;gap:40px;align-items:flex-end}
    .sign img{max-height:90px;max-width:220px;display:block}.line{border-top:1px solid #111;padding-top:4px;min-width:240px}
    .small{font-size:11px;color:#555}.warn{color:#b00020;font-weight:bold}
    @media print{.noprint{display:none}body{margin:16mm}}
</style></head><body>
<p class="noprint"><button onclick="window.print()">Imprimir</button></p>
<div class="head"><div><h1><?= e($business ?: 'Historia clínica') ?></h1></div><div><?= e($d->issued_at->locale('es')->translatedFormat('d \d\e F \d\e Y')) ?></div></div>
<h2><?= e($d->title) ?></h2>
<div class="body"><?= e($d->body) ?></div>
<?php if ($d->kind === 'consent'): ?>
<div class="sign" style="margin-top:50px"><div class="line"><?= e(Mode::term('patient', $patient->kind)) ?> o representante: <?= e($patient->name) ?><br>Firma y documento</div></div>
<?php endif ?>
<div class="sign">
    <div>
        <?php if ($signature): ?><img src="<?= $signature ?>" alt="Firma"><?php endif ?>
        <div class="line"><strong><?= e($d->professional_name) ?></strong><br><?= e($profile?->specialty) ?><?= $profile?->license ? ' · Mat. ' . e($profile->license) : '' ?></div>
    </div>
    <?php if ($seal): ?><div><img src="<?= $seal ?>" alt="Sello"></div><?php endif ?>
</div>
<p class="small" style="margin-top:26px">Emitido en el sistema el <?= e($d->issued_at->format('d/m/Y H:i')) ?> · Código de integridad <?= e($d->short_hash) ?>
    <?php if (!$integrity): ?><span class="warn"> · ATENCIÓN: el contenido no coincide con lo emitido</span><?php endif ?></p>
</body></html>
