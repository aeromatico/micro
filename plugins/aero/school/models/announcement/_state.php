<?php $map = ['draft' => ['Borrador', '#999'], 'sending' => ['Enviando', '#d9822b'], 'sent' => ['Enviado', '#2e9e5b']]; [$t, $c] = $map[$value] ?? [$value, '#999']; ?>
<span style="color:<?= $c ?>;font-weight:600"><?= e($t) ?></span>
