<?php
use Aero\Tickets\Classes\Currency;
use Aero\Tickets\Models\{Order, Payment, PaymentEvent, PaymentProof};
$p = $formModel;
$o = Order::find($p->order_id);
$m = fn ($v) => Currency::format($v, $p->currency);
$proofs = PaymentProof::where('payment_id', $p->id)->get(['id', 'name', 'mime', 'size', 'sha256', 'created_at']);
$hist = PaymentEvent::where('payment_id', $p->id)->orWhere(fn ($q) => $q->whereNull('payment_id')->where('order_id', $p->order_id))->orderBy('id')->get();
$cls = in_array($p->status, ['approved', 'refunded'], true) ? 'success' : (in_array($p->status, ['rejected', 'refund_pending'], true) || $p->is_suspicious ? 'danger' : 'info');
?>
<div class="callout callout-<?= $cls ?>"><div class="content">
    <p><strong>Pago #<?= (int) $p->id ?></strong> · <?= e($p->method_label) ?> · <strong><?= e($p->status_label) ?></strong>
       <?= $p->is_late ? ' · <strong>TARDÍO</strong>' : '' ?><?= $p->is_suspicious ? ' · <strong>SOSPECHOSO</strong>' : '' ?></p>
    <?php if ($p->is_suspicious): ?><p class="text-danger">⚠ <?= e($p->flag_reason) ?></p><?php endif ?>
    <p><strong>Esperado:</strong> <?= e($m($p->amount)) ?>
       <?php if ($p->amount_received !== null): ?>· <strong>Verificado:</strong> <?= e($m($p->amount_received)) ?><?php endif ?>
       · <strong>Intento:</strong> <?= e($p->created_at?->format('d/m/Y H:i:s')) ?>
       <?php if ($p->submitted_at): ?>· <strong>Informado:</strong> <?= e($p->submitted_at->format('d/m/Y H:i:s')) ?><?php endif ?></p>
    <p><strong>Referencia informada:</strong> <?= e($p->reference ?: '—') ?> · <strong>Titular:</strong> <?= e($p->sender_name ?: '—') ?>
       <?php if ($p->provider): ?>· <strong>Proveedor:</strong> <?= e($p->provider) ?> (<?= e($p->provider_ref) ?>)<?php endif ?></p>
    <?php if ($p->reject_reason): ?><p><strong>Motivo de rechazo:</strong> <?= e($p->reject_reason) ?></p><?php endif ?>
    <?php if ($p->verified_at): ?><p><strong>Verificado por:</strong> <?= e($p->verified_by_name ?: ($p->verified_by_actor === 'gateway' ? 'Proveedor de pago (automático)' : '—')) ?> el <?= e($p->verified_at->format('d/m/Y H:i')) ?></p><?php endif ?>
    <?php if ($p->refund_reference): ?><p><strong>Reembolso:</strong> <?= e($p->refund_reference) ?> (<?= e($p->refunded_at?->format('d/m/Y H:i')) ?>)</p><?php endif ?>

    <?php if ($o): ?>
        <h5>Orden</h5>
        <p><a href="<?= Backend::url('aero/tickets/orders/update/' . $o->id) ?>"><strong><?= e($o->code) ?></strong></a> · <?= e($o->status_label) ?> · total <?= e($m($o->total)) ?> ·
           <?= e($o->buyer_name) ?> · <?= e($o->buyer_email) ?> · <?= e($o->buyer_phone) ?></p>
    <?php endif ?>

    <h5>Comprobantes <small class="text-muted">— lo que sube el comprador NO prueba el cobro: verifica el dinero en tu banco.</small></h5>
    <?php if ($proofs->isEmpty()): ?><p class="text-muted">Sin comprobante.</p><?php endif ?>
    <?php foreach ($proofs as $pr): ?>
        <p><a href="<?= Backend::url('aero/tickets/payments/proof/' . $pr->id) ?>" target="_blank" rel="noopener"><?= e($pr->name) ?></a>
           <span class="text-muted">(<?= e($pr->mime) ?>, <?= round($pr->size / 1024) ?> KB, <?= e($pr->created_at?->format('d/m H:i')) ?>)</span></p>
    <?php endforeach ?>

    <h5>Historial de verificaciones</h5>
    <table class="table"><thead><tr><th>Fecha</th><th>Acción</th><th>Responsable</th><th>Estado</th><th>Monto</th><th>Nota</th></tr></thead><tbody>
    <?php foreach ($hist as $h): ?>
        <tr><td><?= e($h->created_at?->format('d/m/Y H:i:s')) ?></td><td><?= e($h->action_label) ?></td>
            <td><?= e(['buyer' => 'Comprador', 'admin' => 'Administrador', 'system' => 'Sistema', 'gateway' => 'Proveedor'][$h->actor_type] ?? $h->actor_type) ?><?= $h->actor_name ? ': ' . e($h->actor_name) : '' ?></td>
            <td><?= e(trim(($h->from_status ? (Payment::$statuses[$h->from_status] ?? $h->from_status) . ' → ' : '') . ($h->to_status ? (Payment::$statuses[$h->to_status] ?? $h->to_status) : ''))) ?></td>
            <td><?= $h->amount !== null ? e($m($h->amount)) : '' ?></td><td><?= e($h->note) ?></td></tr>
    <?php endforeach ?>
    </tbody></table>
</div></div>
