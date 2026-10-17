<?php /** @var string $title */ ?><!doctype html>
<html lang="es"><head><meta charset="utf-8">
<title><?= e($title) ?> — <?= e($patient->name) ?></title>
<style>
    body{font-family:Arial,Helvetica,sans-serif;color:#111;margin:28px;font-size:13px;line-height:1.45}
    h1{font-size:18px;margin:0}h2{font-size:13px;text-transform:uppercase;letter-spacing:.05em;margin:18px 0 4px;border-bottom:1px solid #999;padding-bottom:2px}
    .head{display:flex;justify-content:space-between;border-bottom:2px solid #111;padding-bottom:8px;margin-bottom:10px}
    table{border-collapse:collapse}td,th{padding:3px 12px 3px 0;text-align:left;vertical-align:top}
    .item{margin:0 0 10px;padding-bottom:8px;border-bottom:1px dotted #bbb}.item .m{font-weight:bold;font-size:14px}
    .pre{white-space:pre-wrap}.sign{margin-top:46px;display:flex;gap:40px;align-items:flex-end}
    .sign img{max-height:90px;max-width:220px;display:block}.line{border-top:1px solid #111;padding-top:4px;min-width:240px}
    .small{font-size:11px;color:#555}.warn{color:#b00020;font-weight:bold}
    @media print{.noprint{display:none}body{margin:12mm}}
</style></head>
<body>
<p class="noprint"><button onclick="window.print()">Imprimir</button></p>
<div class="head">
    <div><h1><?= e($business ?: 'Historia clínica') ?></h1><div class="small"><?= e($title) ?></div></div>
    <div style="text-align:right"><strong><?= e($c->signed_at->format('d/m/Y H:i')) ?></strong></div>
</div>
<table>
    <tr><th><?= e(\Aero\Medical\Classes\Mode::term('patient', $patient->kind)) ?></th><td><?= e($patient->name) ?> <?= $patient->age_label ? '(' . e($patient->age_label) . ')' : '' ?></td>
        <th>Documento</th><td><?= e($patient->document ?: '—') ?></td></tr>
    <?php if ($patient->kind === \Aero\Medical\Classes\Mode::ANIMAL): ?>
    <tr><th>Especie / raza</th><td><?= e(trim((\Aero\Medical\Classes\Mode::species()[$patient->species] ?? '') . ' ' . $patient->breed)) ?: '—' ?></td>
        <th>Microchip</th><td><?= e($patient->microchip ?: '—') ?></td></tr>
    <?php endif ?>
    <?php if (trim((string) $patient->allergies) !== ''): ?><tr><th>Alergias</th><td colspan="3"><strong><?= e($patient->allergies) ?></strong></td></tr><?php endif ?>
</table>
