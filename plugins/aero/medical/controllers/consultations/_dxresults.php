<?php
$btn = function ($d, $source) { ?>
    <button type="button" class="btn btn-default btn-xs" style="margin:2px 4px 2px 0;text-align:left"
        data-request="onAddDx" data-request-data="source: '<?= e($source) ?>', code: '<?= e($d['code']) ?>', catalog_id: <?= (int) ($d['id'] ?? 0) ?>">
        <strong><?= e($d['code']) ?></strong> <?= e($d['label']) ?></button>
<?php };
$any = false;
if (count($dxResults['frequent'])): $any = true; ?>
    <div class="text-muted" style="font-size:12px;margin-top:6px">Frecuentes</div>
    <?php foreach ($dxResults['frequent'] as $f) { $btn(['code' => $f->code, 'label' => $f->label, 'id' => $f->id], $f->source === 'cie10' ? 'cie10' : 'custom'); } ?>
<?php endif;
if (count($dxResults['custom'])): $any = true; ?>
    <div class="text-muted" style="font-size:12px;margin-top:6px">Del negocio</div>
    <?php foreach ($dxResults['custom'] as $f) { $btn(['code' => $f->code, 'label' => $f->label, 'id' => $f->id], $f->source === 'cie10' ? 'cie10' : 'custom'); } ?>
<?php endif;
if (count($dxResults['cie'])): $any = true; ?>
    <div class="text-muted" style="font-size:12px;margin-top:6px">CIE-10</div>
    <?php foreach ($dxResults['cie'] as $f) { $btn(['code' => $f->code, 'label' => $f->title], 'cie10'); } ?>
<?php endif;
if (!$any && !empty($searched)): ?>
    <p class="text-muted" style="font-size:12px">Sin resultados. Puede escribirlo como texto libre.</p>
<?php endif ?>
