<?php foreach ($medResults as $m): ?>
    <button type="button" class="btn btn-default btn-xs mc-pick" style="margin:2px 4px 2px 0"
        data-source="<?= e($m['source']) ?>" data-ref="<?= (int) $m['id'] ?>" data-text="<?= e($m['text']) ?>" data-ingredient="<?= e($m['ingredient'] ?? '') ?>" data-strength="<?= e($m['strength'] ?? '') ?>" data-presentation="<?= e($m['presentation'] ?? '') ?>"><?= e($m['text']) ?>
        <small class="text-muted"><?= $m['source'] === 'liname' ? 'LINAME' : 'del negocio' ?></small></button>
<?php endforeach ?>
