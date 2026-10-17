<?= $this->makePartial('print_head', ['title' => 'Órdenes de exámenes', 'c' => $c, 'patient' => $patient, 'business' => $business]) ?>
<h2>Órdenes de exámenes</h2>
<?php foreach ($doc->items as $i): ?>
    <div class="item">
        <div class="m"><?= e($i->exam_name) ?> <span class="small">(<?= e(\Aero\Medical\Models\ExamCatalog::CATEGORIES[$i->category] ?? $i->category) ?>)</span></div>
        <?php if ($i->instructions): ?><div class="pre"><?= e($i->instructions) ?></div><?php endif ?>
    </div>
<?php endforeach ?>
<?php if ($doc->notes): ?><h2>Notas</h2><div class="pre"><?= e($doc->notes) ?></div><?php endif ?>
<?= $this->makePartial('print_foot', ['c' => $c, 'profile' => $profile, 'signature' => $signature, 'seal' => $seal, 'integrity' => $integrity]) ?>
