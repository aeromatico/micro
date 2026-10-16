<?php $replies = $formModel->replies()->with(['user', 'frontendUser'])->get(); ?>
<?php if ($replies->isEmpty()): ?>
    <p class="text-muted">Todavía no hay respuestas.</p>
<?php else: foreach ($replies as $r): ?>
    <div style="<?= Aero\Crm\Classes\Md::bubbleStyle($r->is_internal ? 'internal' : ($r->author_type === 'customer' ? 'customer' : 'agent')) ?>">
        <div class="text-muted" style="font-size:12px">
            <strong><?= e($r->author_label) ?></strong>
            <?php if ($r->author_type === 'customer'): ?>· <em>cliente</em><?php endif ?>
            · <?= $r->created_at->format('d/m/Y H:i') ?>
            <?php if ($r->is_internal): ?>· <em>nota interna</em><?php endif ?>
        </div>
        <div><?= $r->body_html ?></div>
    </div>
<?php endforeach; endif ?>
