<?php foreach ($medResults as $m): ?>
    <button type="button" class="btn btn-default btn-xs mc-pick" style="margin:2px 4px 2px 0"
        data-text="<?= e($m['text']) ?>" data-ingredient="<?= e($m['ingredient'] ?? '') ?>" data-strength="<?= e($m['strength'] ?? '') ?>" data-presentation="<?= e($m['presentation'] ?? '') ?>"><?= e($m['text']) ?></button>
<?php endforeach ?>
