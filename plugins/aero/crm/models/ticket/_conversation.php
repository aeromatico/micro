<?php $replies = $formModel->replies()->with('user')->get(); ?>
<?php if ($replies->isEmpty()): ?>
    <p class="text-muted">Todavía no hay respuestas.</p>
<?php else: foreach ($replies as $r): ?>
    <div style="margin-bottom:10px;padding:8px 10px;border-radius:6px;background:<?= $r->is_internal ? '#fef9c3' : '#f1f5f9' ?>">
        <div class="text-muted" style="font-size:12px">
            <strong><?= e(trim(($r->user->first_name ?? '') . ' ' . ($r->user->last_name ?? '')) ?: 'Sistema') ?></strong>
            · <?= $r->created_at->format('d/m/Y H:i') ?>
            <?php if ($r->is_internal): ?>· <em>nota interna</em><?php endif ?>
        </div>
        <div><?= nl2br(e($r->body)) ?></div>
    </div>
<?php endforeach; endif ?>
