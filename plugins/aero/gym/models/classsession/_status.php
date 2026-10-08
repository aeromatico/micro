<?php $map = ['scheduled' => ['Programada', '#2e9e5b'], 'cancelled' => ['Cancelada', '#c0392b'], 'done' => ['Realizada', '#999']]; [$t, $c] = $map[$record->status] ?? [$record->status, '#999']; ?>
<span style="color:<?= $c ?>;font-weight:600"><?= e($t) ?></span>
