<?= $this->makePartial('print_head', ['title' => 'Receta', 'c' => $c, 'patient' => $patient, 'business' => $business]) ?>
<h2>Receta</h2>
<?php foreach ($doc->items as $i): ?>
    <div class="item">
        <div class="m"><?= e($i->medication) ?></div>
        <div><?= e(implode(' · ', array_filter([$i->dose ? 'Dosis: ' . $i->dose : null, $i->route ? 'Vía: ' . $i->route : null, $i->frequency ? 'Frecuencia: ' . $i->frequency : null, $i->duration ? 'Duración: ' . $i->duration : null, $i->quantity ? 'Cantidad: ' . $i->quantity : null]))) ?></div>
        <?php if ($i->instructions): ?><div class="pre"><?= e($i->instructions) ?></div><?php endif ?>
    </div>
<?php endforeach ?>
<?php if ($doc->notes): ?><h2>Notas</h2><div class="pre"><?= e($doc->notes) ?></div><?php endif ?>
<?= $this->makePartial('print_foot', ['c' => $c, 'profile' => $profile, 'signature' => $signature, 'seal' => $seal, 'integrity' => $integrity]) ?>
