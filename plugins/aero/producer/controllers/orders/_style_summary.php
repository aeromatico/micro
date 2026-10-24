<?php $style = $formModel->style ?: ($formModel->style_id ? \Aero\Producer\Models\Style::find($formModel->style_id) : null) ?>
<?php if (!$style): ?>
    <p class="flash-message static warning">Elige primero un estilo en <a href="<?= Backend::url('aero/producer/studio') ?>">Nuevo video</a>.</p>
<?php else: ?>
    <div class="callout callout-info" style="margin-bottom:16px">
        <div class="header"><h3><?= e($style->name) ?> <small style="font-weight:400">· <?= e($style->kind_label) ?></small></h3></div>
        <div class="content">
            <p><?= e($style->description) ?></p>
            <?php $quote = \Aero\Producer\Classes\Billing::quote($style, \Aero\Producer\Classes\CurrentTenant::id()) ?>
            <p>Hasta <strong><?= (int) $style->max_seconds ?> s</strong> ·
                <strong><?= $quote ? $quote['cost'] . ' ' . e(mb_strtolower($quote['type'])) : 'sin costo' ?></strong> por video
                <?php if ($quote && $quote['balance'] !== null): ?>
                    · tu saldo: <strong class="<?= $quote['balance'] < $quote['cost'] ? 'text-danger' : '' ?>"><?= (int) $quote['balance'] ?></strong>
                <?php endif ?>
            </p>
            <?php if ($quote): ?>
                <p class="help-block">Se cobra al enviar. Si el video no se puede producir, los créditos vuelven solos.</p>
            <?php endif ?>
            <?php if ($formModel->exists): ?>
                <p>Estado: <strong><?= e($formModel->status_label) ?></strong><?= $formModel->refunded_at ? ' · créditos devueltos' : '' ?></p>
            <?php endif ?>
        </div>
    </div>
    <?php if ($formModel->status === 'ready' && $formModel->output_url): ?>
        <video src="<?= e($formModel->output_url) ?>" controls playsinline style="max-width:320px;width:100%;border-radius:8px;margin-bottom:16px"></video>
    <?php elseif ($formModel->status === 'failed' && $formModel->error_message): ?>
        <p class="flash-message static error"><?= e($formModel->error_message) ?></p>
    <?php endif ?>
<?php endif ?>
