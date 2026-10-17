<?php
use Aero\Medical\Classes\Mode;
use Aero\Medical\Classes\VitalsSchema;
$content = (array) $c->content;
$v = $c->vitals;
?><!doctype html>
<html lang="es"><head><meta charset="utf-8">
<title><?= e($c->kind_label) ?> — <?= e($patient->name) ?></title>
<style>
    body{font-family:Arial,Helvetica,sans-serif;color:#111;margin:28px;font-size:13px;line-height:1.45}
    h1{font-size:18px;margin:0}h2{font-size:13px;text-transform:uppercase;letter-spacing:.05em;margin:18px 0 4px;border-bottom:1px solid #999;padding-bottom:2px}
    .head{display:flex;justify-content:space-between;border-bottom:2px solid #111;padding-bottom:8px;margin-bottom:10px}
    table{border-collapse:collapse}td,th{padding:3px 12px 3px 0;text-align:left;vertical-align:top}
    .pre{white-space:pre-wrap}.sign{margin-top:46px;display:flex;gap:40px;align-items:flex-end}
    .sign img{max-height:90px;max-width:220px;display:block}.line{border-top:1px solid #111;padding-top:4px;min-width:240px}
    .small{font-size:11px;color:#555}.warn{color:#b00020;font-weight:bold}
    @media print{.noprint{display:none}body{margin:12mm}}
</style></head>
<body>
<p class="noprint"><button onclick="window.print()">Imprimir</button></p>
<div class="head">
    <div><h1><?= e($business ?: 'Historia clínica') ?></h1><div class="small"><?= e($c->kind_label) ?> · <?= e($c->template_name) ?></div></div>
    <div style="text-align:right"><strong><?= e($c->consulted_at->format('d/m/Y H:i')) ?></strong></div>
</div>

<table>
    <tr><th><?= e(Mode::term('patient', $patient->kind)) ?></th><td><?= e($patient->name) ?> <?= $patient->age_label ? '(' . e($patient->age_label) . ')' : '' ?></td>
        <th>Documento</th><td><?= e($patient->document ?: '—') ?></td></tr>
    <?php if ($patient->kind === Mode::ANIMAL): ?>
    <tr><th>Especie / raza</th><td><?= e(trim((\Aero\Medical\Classes\Mode::species()[$patient->species] ?? '') . ' ' . $patient->breed)) ?: '—' ?></td>
        <th>Microchip</th><td><?= e($patient->microchip ?: '—') ?></td></tr>
    <?php endif ?>
    <?php if (trim((string) $patient->allergies) !== ''): ?>
    <tr><th>Alergias</th><td colspan="3"><strong><?= e($patient->allergies) ?></strong></td></tr>
    <?php endif ?>
</table>

<?php foreach ($c->sectionList() as $s): ?>
    <?php if ($s['type'] === 'vitals'): ?>
        <?php $rows = []; foreach ($vitalFields as $n => $f) { $t = VitalsSchema::format($n, $v?->{$n}); if ($t !== '') $rows[$f['label']] = $t; } ?>
        <?php if ($rows): ?>
        <h2><?= e($s['label']) ?></h2>
        <table><?php foreach (array_chunk($rows, 3, true) as $chunk): ?><tr><?php foreach ($chunk as $l => $t): ?><th><?= e($l) ?></th><td><?= e($t) ?></td><?php endforeach ?></tr><?php endforeach ?></table>
        <?php endif ?>
    <?php elseif (trim((string) ($content[$s['key']] ?? '')) !== ''): ?>
        <h2><?= e($s['label']) ?></h2><div class="pre"><?= e($content[$s['key']]) ?></div>
    <?php endif ?>
<?php endforeach ?>

<?php if (count($c->addenda)): ?>
    <h2>Adendas</h2>
    <?php foreach ($c->addenda as $a): ?>
        <div class="small"><?= e($a->user_name) ?> · <?= e($a->created_at->format('d/m/Y H:i')) ?></div><div class="pre" style="margin-bottom:6px"><?= e($a->body) ?></div>
    <?php endforeach ?>
<?php endif ?>

<div class="sign">
    <div>
        <?php if ($signature): ?><img src="<?= $signature ?>" alt="Firma"><?php endif ?>
        <div class="line"><strong><?= e($c->professional_name) ?></strong><br>
            <?= e($profile?->specialty) ?><?= $profile?->license ? ' · Mat. ' . e($profile->license) : '' ?></div>
    </div>
    <?php if ($seal): ?><div><img src="<?= $seal ?>" alt="Sello"></div><?php endif ?>
</div>
<p class="small" style="margin-top:22px">
    Nota firmada en el sistema el <?= e($c->signed_at->format('d/m/Y H:i')) ?> · Código de integridad <?= e($c->short_hash) ?>
    <?php if (!$integrity): ?><span class="warn"> · ATENCIÓN: el contenido no coincide con lo firmado</span><?php endif ?>
</p>
</body></html>
