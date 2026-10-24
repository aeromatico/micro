<?php $style = $formModel->style ?: ($formModel->style_id ? \Aero\Producer\Models\Style::find($formModel->style_id) : null) ?>
<?php if (!$style): ?>
    <p class="flash-message static warning">Elige primero un estilo en <a href="<?= Backend::url('aero/producer/studio') ?>">Nuevo video</a>.</p>
<?php else: ?>
    <div class="callout callout-info" style="margin-bottom:16px">
        <div class="header"><h3><?= e($style->name) ?> <small style="font-weight:400">· <?= e($style->kind_label) ?></small></h3></div>
        <div class="content">
            <p><?= e($style->description) ?></p>
            <p>Hasta <strong><?= (int) $style->max_seconds ?> s</strong> ·
                <strong><?= $style->credit_cost > 0 ? (int) $style->credit_cost . ' créditos' : 'sin costo' ?></strong> por video</p>
            <?php if ($formModel->exists): ?>
                <p>Estado: <strong><?= e($formModel->status_label) ?></strong></p>
            <?php endif ?>
        </div>
    </div>
    <?php if ($formModel->status === 'ready' && $formModel->output_url): ?>
        <video src="<?= e($formModel->output_url) ?>" controls playsinline style="max-width:320px;width:100%;border-radius:8px;margin-bottom:16px"></video>
    <?php elseif ($formModel->status === 'failed' && $formModel->error_message): ?>
        <p class="flash-message static error"><?= e($formModel->error_message) ?></p>
    <?php endif ?>
<?php endif ?>
