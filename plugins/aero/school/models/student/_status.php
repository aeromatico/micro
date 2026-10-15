<?php $map = ['active' => ['Inscrito', '#2e9e5b'], 'retired' => ['Retirado', '#d9822b'], 'graduated' => ['Egresado', '#4a6fa5']]; [$t, $c] = $map[$value] ?? [$value, '#999']; ?>
<span style="color:<?= $c ?>;font-weight:600"><?= e($t) ?></span>
