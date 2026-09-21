<?php $replies = $formModel->replies()->with(['user', 'frontendUser'])->get(); ?>
<?php if ($replies->isEmpty()): ?>
    <p class="text-muted">Todavía no hay respuestas.</p>
<?php else: foreach ($replies as $r): ?>
    <div style="margin-bottom:10px;padding:8px 10px;border-radius:6px;background:<?= $r->is_internal ? '#fef9c3' : ($r->author_type === 'customer' ? '#eef2ff' : '#f1f5f9') ?>">
        <div class="text-muted" style="font-size:12px">
            <strong><?= e($r->author_label) ?></strong>
            <?php if ($r->author_type === 'customer'): ?>· <em>cliente</em><?php endif ?>
            · <?= $r->created_at->format('d/m/Y H:i') ?>
            <?php if ($r->is_internal): ?>· <em>nota interna</em><?php endif ?>
        </div>
        <div><?= nl2br(e($r->body)) ?></div>
    </div>
<?php endforeach; endif ?>
