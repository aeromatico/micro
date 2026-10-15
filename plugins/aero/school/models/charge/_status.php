<?php
$overdue = in_array($value, ['pending', 'partial'], true) && $record->due_on && $record->due_on->lt(today());
$map = ['pending' => ['Pendiente', '#d9822b'], 'partial' => ['Parcial', '#d9822b'], 'paid' => ['Pagada', '#2e9e5b'], 'void' => ['Anulada', '#999']];
[$t, $c] = $map[$value] ?? [$value, '#999'];
if ($overdue) { $t = 'Vencida'; $c = '#c0392b'; }
?>
<span style="color:<?= $c ?>;font-weight:600"><?= e($t) ?></span>
