<?php foreach ($medResults as $m): ?>
    <button type="button" class="btn btn-default btn-xs mc-pick" style="margin:2px 4px 2px 0"
        data-source="<?= e($m['source']) ?>" data-ref="<?= (int) $m['id'] ?>" data-text="<?= e($m['text']) ?>"><?= e($m['text']) ?>
        <small class="text-muted"><?= $m['source'] === 'liname' ? 'LINAME' : 'del negocio' ?></small></button>
<?php endforeach ?>
