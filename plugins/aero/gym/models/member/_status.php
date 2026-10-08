<?php $map = ['active' => ['Activo', '#2e9e5b'], 'suspended' => ['Suspendido', '#d9822b'], 'inactive' => ['Inactivo', '#999']]; [$t, $c] = $map[$value] ?? [$value, '#999']; ?>
<span style="color:<?= $c ?>;font-weight:600"><?= e($t) ?></span>
