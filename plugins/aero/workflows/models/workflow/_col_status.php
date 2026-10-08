<?php $labels = ['draft' => ['Borrador', '#9a6a00'], 'published' => ['Publicado', '#178250'], 'archived' => ['Archivado', '#6b7a8c']];
[$text, $color] = $labels[$value] ?? [$value, '#6b7a8c']; ?>
<span style="font-weight:600;color:<?= $color ?>"><?= e($text) ?></span>
