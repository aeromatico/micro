<?php $states = ['open' => 'Abierto', 'pending' => 'En espera', 'resolved' => 'Resuelto', 'closed' => 'Cerrado']; ?>
<div class="layout-row"><div class="padded-container" style="max-width:820px">
<p><a href="<?= Backend::url('aero/crm/support') ?>">← Mis tickets</a></p>
<h3><?= e($ticket->number) ?> · <?= e($ticket->subject) ?></h3>
<p class="text-muted"><?= e($ticket->department?->name) ?> · <?= e($states[$ticket->status] ?? $ticket->status) ?> · <?= $ticket->created_at->format('d/m/Y H:i') ?></p>
<?php if ($ticket->description): ?><div style="<?= Aero\Crm\Classes\Md::bubbleStyle('agent') ?>"><?= $ticket->description_html ?></div><?php endif ?>
<?php foreach ($ticket->replies()->where('is_internal', false)->with('user')->get() as $r): ?>
    <div style="<?= Aero\Crm\Classes\Md::bubbleStyle($r->author_type === 'customer' ? 'customer' : 'agent') ?>">
        <div class="text-muted" style="font-size:12px"><strong><?= e($r->author_type === 'customer' ? ($r->author_name ?: 'Tú') : $r->public_label . ' · Soporte') ?></strong> · <?= $r->created_at->format('d/m/Y H:i') ?></div>
        <div><?= $r->body_html ?></div>
    </div>
<?php endforeach ?>
<?= Form::open(['data-request' => 'onReply']) ?>
    <div class="form-group"><textarea name="body" rows="4" class="form-control" placeholder="Escribe tu respuesta... (admite Markdown: **negrita**, listas, `código`)"></textarea></div>
    <button type="submit" class="btn btn-primary">Responder</button>
<?= Form::close() ?>
</div></div>
