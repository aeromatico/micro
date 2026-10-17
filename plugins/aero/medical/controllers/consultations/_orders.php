<?php if (!$order || !count($order->items)): ?>
    <p class="text-muted" style="margin:0 0 8px">Sin órdenes.</p>
<?php else: ?>
<table class="table table-condensed" style="margin-bottom:8px">
<?php foreach ($order->items as $i): ?>
    <tr>
        <td><strong><?= e($i->exam_name) ?></strong>
            <small class="text-muted">· <?= e(\Aero\Medical\Models\ExamCatalog::CATEGORIES[$i->category] ?? $i->category) ?></small>
            <?= $i->instructions ? '<div class="text-muted" style="font-size:12px">' . e($i->instructions) . '</div>' : '' ?></td>
        <?php if ($editable): ?>
        <td class="text-right"><button type="button" class="btn btn-danger btn-xs" data-request="onRemoveOrder" data-request-data="item_id: <?= (int) $i->id ?>">Quitar</button></td>
        <?php endif ?>
    </tr>
<?php endforeach ?>
</table>
<?php endif ?>
