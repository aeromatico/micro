<?php $map = ['pending' => ['Pendiente de pago', '#d9822b'], 'active' => ['Activa', '#2e9e5b'], 'expired' => ['Vencida', '#c0392b'], 'cancelled' => ['Cancelada', '#999']]; [$t, $c] = $map[$value] ?? [$value, '#999']; ?>
<span style="color:<?= $c ?>;font-weight:600"><?= e($t) ?></span>
