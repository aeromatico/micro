<?php
$d = \Aero\Tickets\Classes\TemplateDefaults::all()[$formModel->code] ?? null;
$vars = $d ? array_keys($d['sample']) : [];
$preview = $d ? \Aero\Tickets\Classes\Templates::fill($formModel->body ?: $d['body'], $d['sample'], true) : '';
?>
<div class="callout callout-info"><div class="content">
    <p><strong>Variables:</strong> <?php foreach ($vars as $v): ?><code>{{<?= e($v) ?>}}</code> <?php endforeach ?></p>
    <?php if ($d): ?><p><strong>Vista previa con datos de ejemplo:</strong></p>
    <div style="border:1px solid #ddd;border-radius:8px;padding:12px;background:#fff"><?= $preview ?></div>
    <p class="help-block">Asunto: <?= e(\Aero\Tickets\Classes\Templates::fill($formModel->subject, $d['sample'], false)) ?></p><?php endif ?>
</div></div>
