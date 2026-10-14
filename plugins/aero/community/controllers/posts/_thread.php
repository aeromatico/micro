<?php
    $myVote = \Aero\Community\Models\Vote::where('post_id', $post->id)->where('user_id', BackendAuth::getUser()->id)->exists();
    $comments = $post->comments()->orderBy('created_at')->get();
    $locked = $post->is_locked && !\Aero\Community\Classes\CurrentTenant::isAdmin();
?>
<div class="aco-thread" style="max-width:820px">
    <h3 style="margin:0 0 6px"><?= e($post->title) ?></h3>
    <p class="text-muted" style="margin:0 0 14px">
        <?= e($post->type_label) ?> · <?= e($post->status_label) ?>
        · <?= e($post->author_name) ?> · <?= e($post->created_at->format('d/m/Y H:i')) ?>
        <?php if ($post->visibility === 'private'): ?> · <strong>Privado</strong><?php endif ?>
        <?php if ($post->sla_label): ?> · <?= e($post->sla_label) ?><?php endif ?>
    </p>

    <div style="white-space:pre-wrap;margin-bottom:14px"><?= e($post->body) ?></div>

    <?php if ($post->page_url || $post->area): ?>
        <p class="text-muted" style="font-size:12px">
            <?php if ($post->area): ?>Área: <?= e($post->area) ?><br><?php endif ?>
            <?php if ($post->page_url): ?>Pantalla: <?= e($post->page_url) ?><?php endif ?>
        </p>
    <?php endif ?>

    <?php if ($post->screenshot): ?>
        <p><a href="<?= Backend::url('aero/community/posts/screenshot/' . $post->id) ?>" target="_blank" rel="noopener">
            <img src="<?= Backend::url('aero/community/posts/screenshot/' . $post->id) ?>" alt="Captura" style="max-width:100%;border:1px solid #ddd;border-radius:6px">
        </a></p>
    <?php endif ?>

    <?php if (!empty($post->context['errors'])): ?>
        <details style="margin-bottom:14px"><summary class="text-muted">Errores del navegador al reportar</summary>
            <pre style="white-space:pre-wrap"><?= e(implode("\n", $post->context['errors'])) ?></pre>
        </details>
    <?php endif ?>

    <?php if ($post->official_response): ?>
        <div style="border-left:4px solid #2b8a3e;background:#f1f8f3;padding:12px 14px;margin:0 0 18px;border-radius:4px">
            <strong>Respuesta del equipo</strong>
            <div style="white-space:pre-wrap;margin-top:4px"><?= e($post->official_response) ?></div>
        </div>
    <?php elseif ($post->sla_due_at): ?>
        <p class="text-muted">Nos comprometemos a responder esto antes del <?= e($post->sla_due_at->format('d/m/Y')) ?>.</p>
    <?php endif ?>

    <?php if ($post->visibility === 'public'): ?>
        <p>
            <button type="button" class="btn <?= $myVote ? 'btn-primary' : 'btn-default' ?>"
                data-request="onVote" data-request-data="id: <?= (int) $post->id ?>">
                ▲ <?= $myVote ? 'Quitar mi voto' : 'Me interesa' ?> (<?= (int) $post->votes_count ?>)
            </button>
        </p>
    <?php endif ?>

    <h4 style="margin-top:22px">Comentarios (<?= $comments->count() ?>)</h4>
    <?php foreach ($comments as $c): ?>
        <div style="padding:10px 0;border-bottom:1px solid #eee">
            <strong><?= e($c->author_name) ?></strong>
            <?php if ($c->is_official): ?><span class="label label-success">Equipo</span><?php endif ?>
            <span class="text-muted"> · <?= e($c->created_at->format('d/m/Y H:i')) ?></span>
            <div style="white-space:pre-wrap"><?= e($c->body) ?></div>
        </div>
    <?php endforeach ?>

    <?php if ($locked): ?>
        <p class="text-muted" style="margin-top:12px">Esta conversación está cerrada.</p>
    <?php else: ?>
        <div style="margin-top:14px">
            <textarea name="body" id="aco-comment" class="form-control" rows="3" maxlength="4000" placeholder="Escribe un comentario…"></textarea>
            <button type="button" class="btn btn-primary" style="margin-top:8px"
                data-request="onComment" data-request-data="id: <?= (int) $post->id ?>">Comentar</button>
        </div>
    <?php endif ?>
</div>
