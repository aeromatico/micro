<?php
$colors = ['pending' => '#d97706', 'confirmed' => '#2563eb', 'in_service' => '#7c3aed', 'completed' => '#16a34a',
           'cancelled' => '#6b7280', 'rejected' => '#dc2626', 'no_show' => '#9a3412'];
$s = $record->status;
?>
<span style="display:inline-block;padding:1px 8px;border-radius:10px;color:#fff;font-size:11px;background:<?= $colors[$s] ?? '#6b7280' ?>"><?= e($record->status_label) ?></span>
