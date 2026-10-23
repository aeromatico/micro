<?php
$svc = app(\Aero\Church\Classes\AnnouncementService::class);
$preview = $model->status === 'draft' ? $svc->preview($model) : null;
$recips = $model->status !== 'draft' ? $model->recipients()->with('member')->orderBy('id')->limit(300)->get() : collect();
?>
<div class="form-group" style="margin-top:20px">
    <?php if ($model->status === 'draft'): ?>
        <h4>Antes de enviar</h4>
        <p>Lo recibirían <strong><?= (int) $preview['recipients'] ?></strong> personas.
           <?php if ($preview['skipped']): ?><span class="text-muted"><?= (int) $preview['skipped'] ?> quedan fuera por no tener consentimiento o teléfono (los menores reciben el aviso a través de su tutor).</span><?php endif ?></p>
        <button type="button" class="btn btn-primary" data-request="onSend" data-request-confirm="¿Enviar este comunicado por WhatsApp a <?= (int) $preview['recipients'] ?> personas? No se puede deshacer.">Enviar por WhatsApp</button>
    <?php else: ?>
        <h4>Resultado: <?= e(\Aero\Church\Models\Announcement::STATUSES[$model->status] ?? $model->status) ?></h4>
        <p>Enviados: <strong><?= (int) $model->sent_count ?></strong> · Fallidos: <?= (int) $model->failed_count ?> · Sin consentimiento o teléfono: <?= (int) $model->skipped_count ?><?= $model->sent_at ? ' · ' . e($model->sent_at->format('d/m/Y H:i')) : '' ?></p>
        <?php if ($recips->isNotEmpty()): ?>
        <table class="table data">
            <thead><tr><th>Destinatario</th><th>Estado</th></tr></thead>
            <tbody>
            <?php foreach ($recips as $r): ?>
                <tr><td><?= e($r->member?->full_name ?: $r->phone) ?></td><td><?= $r->status === 'sent' ? 'Enviado' : 'Falló' . ($r->error ? ' — ' . e($r->error) : '') ?></td></tr>
            <?php endforeach ?>
            </tbody>
        </table>
        <?php endif ?>
    <?php endif ?>
</div>
