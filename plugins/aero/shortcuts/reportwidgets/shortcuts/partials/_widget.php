<div class="aero-sc" data-aero-sc data-reorder-url="<?= e($reorderUrl) ?>">
    <?php foreach ($items as $item): ?>
        <div class="aero-sc-item" draggable="true" data-id="<?= $item->id ?>" style="--sc-color:<?= e($item->color ?: '#475569') ?>">
            <a class="aero-sc-link" draggable="false" href="<?= e($item->href) ?>"
               <?php if ($item->opens_new_tab): ?>target="_blank" rel="noopener noreferrer"<?php endif ?>>
                <span class="aero-sc-icon"><i class="<?= e($item->icon) ?>"></i></span>
                <span class="aero-sc-label"><?= e($item->label) ?></span>
                <?php if ($item->opens_new_tab): ?><i class="icon-external-link aero-sc-ext"></i><?php endif ?>
            </a>
            <a class="aero-sc-edit" draggable="false" href="<?= e($manageUrl . '/update/' . $item->id) ?>" title="Editar este botón"><i class="icon-pencil"></i></a>
        </div>
    <?php endforeach ?>
    <a class="aero-sc-item aero-sc-add" draggable="false" href="<?= e($items->isEmpty() ? $manageUrl : $createUrl) ?>">
        <span class="aero-sc-icon"><i class="icon-plus"></i></span>
        <span class="aero-sc-label"><?= $items->isEmpty() ? 'Crear atajos' : 'Añadir botón' ?></span>
    </a>
    <a class="aero-sc-item aero-sc-add" draggable="false" href="<?= e($manageUrl) ?>">
        <span class="aero-sc-icon"><i class="icon-cog"></i></span>
        <span class="aero-sc-label">Administrar</span>
    </a>
</div>
