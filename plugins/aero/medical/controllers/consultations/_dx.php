<?php /** @var \Illuminate\Support\Collection $dxList */ ?>
<?php if (!count($dxList)): ?>
    <p class="text-muted" style="margin:0 0 8px">Sin diagnósticos.</p>
<?php else: ?>
<table class="table table-condensed" style="margin-bottom:8px">
<?php foreach ($dxList as $d): ?>
    <tr>
        <td style="width:28px"><?= $d->is_primary ? '<span title="Principal">★</span>' : '' ?></td>
        <td><strong><?= e($d->code) ?></strong> <?= e($d->label) ?>
            <small class="text-muted">· <?= $d->is_primary ? 'principal' : 'secundario' ?> · <?= e(mb_strtolower($d->certainty_label)) ?><?= $d->source === 'free' ? ' · texto libre' : '' ?></small></td>
        <?php if ($editable): ?>
        <td class="text-right" style="white-space:nowrap">
            <?php if (!$d->is_primary): ?>
                <button type="button" class="btn btn-default btn-xs" data-request="onSetPrimaryDx" data-request-data="dx_id: <?= (int) $d->id ?>">Hacer principal</button>
            <?php endif ?>
            <button type="button" class="btn btn-default btn-xs" data-request="onSetCertaintyDx"
                    data-request-data="dx_id: <?= (int) $d->id ?>, certainty: '<?= $d->certainty === 'confirmed' ? 'presumptive' : 'confirmed' ?>'">
                Marcar <?= $d->certainty === 'confirmed' ? 'presuntivo' : 'confirmado' ?></button>
            <button type="button" class="btn btn-danger btn-xs" data-request="onRemoveDx" data-request-data="dx_id: <?= (int) $d->id ?>">Quitar</button>
        </td>
        <?php endif ?>
    </tr>
<?php endforeach ?>
</table>
<?php endif ?>
