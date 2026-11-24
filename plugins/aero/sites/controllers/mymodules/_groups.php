<?php foreach ($groups as $group): ?>
    <section class="mm-group">
        <h3 class="mm-group-title"><?= e($group['label']) ?></h3>
        <div class="mm-grid">
            <?php foreach ($group['items'] as $item): ?>
                <?= $this->makePartial('card', ['item' => $item]) ?>
            <?php endforeach ?>
        </div>
    </section>
<?php endforeach ?>
