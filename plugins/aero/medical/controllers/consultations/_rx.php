<?php if (!$rx || !count($rx->items)): ?>
    <p class="text-muted" style="margin:0 0 8px">Sin medicamentos.</p>
<?php else: ?>
<table class="table table-condensed" style="margin-bottom:8px">
<?php foreach ($rx->items as $i): ?>
    <tr>
        <td><strong><?= e($i->medication) ?></strong>
            <div class="text-muted" style="font-size:12px">
                <?= e(implode(' · ', array_filter([$i->dose ? 'Dosis: ' . $i->dose : null, $i->route ? 'Vía: ' . $i->route : null, $i->frequency ? 'Cada/Frecuencia: ' . $i->frequency : null, $i->duration ? 'Duración: ' . $i->duration : null, $i->quantity ? 'Cantidad: ' . $i->quantity : null]))) ?>
                <?= $i->instructions ? '<br>' . e($i->instructions) : '' ?></div></td>
        <?php if ($editable): ?>
        <td class="text-right"><button type="button" class="btn btn-danger btn-xs" data-request="onRemoveRx" data-request-data="item_id: <?= (int) $i->id ?>">Quitar</button></td>
        <?php endif ?>
    </tr>
<?php endforeach ?>
</table>
<?php endif ?>
