<?php if ($showTitle): ?><h4 style="margin:0 0 8px"><?= e($title) ?></h4><?php endif ?>
<div class="aero-sc" data-aero-sc data-reorder-url="<?= e($reorderUrl) ?>">
    <?php foreach ($items as $item): ?>
        <a class="aero-sc-item" draggable="true" data-id="<?= $item->id ?>"
           href="<?= e($item->href) ?>"
           <?php if ($item->opens_new_tab): ?>target="_blank" rel="noopener noreferrer"<?php endif ?>
           style="--sc-color:<?= e($item->color ?: '#475569') ?>">
            <span class="aero-sc-icon"><i class="<?= e($item->icon) ?>"></i></span>
            <span class="aero-sc-label"><?= e($item->label) ?></span>
            <?php if ($item->opens_new_tab): ?><i class="icon-external-link aero-sc-ext"></i><?php endif ?>
        </a>
    <?php endforeach ?>
    <a class="aero-sc-item aero-sc-add" href="<?= e($items->isEmpty() ? $manageUrl : $createUrl) ?>">
        <span class="aero-sc-icon"><i class="icon-plus"></i></span>
        <span class="aero-sc-label"><?= $items->isEmpty() ? 'Crear atajos' : 'Añadir' ?></span>
    </a>
</div>
