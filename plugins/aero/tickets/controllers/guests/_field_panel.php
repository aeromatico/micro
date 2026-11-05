<?php
$g = $formModel; $e = $g->event;
$tickets = $g->tickets()->orderBy('id')->get();
?>
<div class="callout callout-<?= $g->isRevoked() ? 'danger' : 'info' ?>"><div class="content">
    <p><strong>Estado de la invitación:</strong> <?= e($g->invitation_status_label) ?>
       · <strong>Lugares:</strong> <?= (int) $g->party_size ?>
       · <strong>Confirmados:</strong> <?= (int) $g->confirmed_count ?>
       <?php if ($g->responded_at): ?>· respondió <?= e($g->responded_at->format('d/m H:i')) ?><?php endif ?></p>
    <?php if ($g->isRevoked()): ?>
        <p>Revocada <?= e($g->revoked_at?->format('d/m/Y H:i')) ?><?= $g->revoke_reason ? ' — ' . e($g->revoke_reason) : '' ?>. El enlace ya no sirve.</p>
    <?php else: ?>
        <p><strong>Enlace privado:</strong> <input type="text" readonly class="form-control" value="<?= e($g->invite_url) ?>" onclick="this.select()"></p>
        <p><a class="btn btn-success" target="_blank" rel="noopener" href="<?= e($g->whatsapp_url) ?>"
              onclick="$.request('onMarkSent',{data:{id:<?= (int) $g->id ?>}})">Enviar por WhatsApp</a></p>
    <?php endif ?>
    <?php if ($tickets->count()): ?>
        <table class="table"><thead><tr><th>Asistente</th><th>Código</th><th>Estado</th><th>Total</th></tr></thead><tbody>
        <?php foreach ($tickets as $t): ?>
            <tr><td><?= e($t->holder_name) ?></td><td><?= e($t->code) ?></td>
                <td><?= e(\Aero\Tickets\Models\Ticket::$statuses[$t->status]) ?><?= $t->refund_pending ? ' · <strong>reembolso pendiente</strong>' : '' ?></td>
                <td><?= $t->amount > 0 ? e(\Aero\Tickets\Classes\Currency::format($t->amount, $t->currency)) : 'Sin costo' ?></td></tr>
        <?php endforeach ?>
        </tbody></table>
    <?php endif ?>
</div></div>
