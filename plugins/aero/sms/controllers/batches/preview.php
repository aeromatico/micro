<?php Block::put('breadcrumb') ?>
    <ul>
        <li><a href="<?= Backend::url('aero/sms/batches') ?>">Lotes</a></li>
        <li><?= e($this->pageTitle) ?></li>
    </ul>
<?php Block::endPut() ?>

<?php $s = $formModel->stats() ?>
<div class="callout callout-info">
    <div class="header"><h3>Progreso</h3></div>
    <div class="content">
        Total <strong><?= $s['total'] ?></strong> ·
        En cola <strong><?= $s['queued'] ?></strong> ·
        Enviados <strong><?= $s['sent'] ?></strong> ·
        Entregados <strong><?= $s['delivered'] ?></strong> ·
        Fallidos <strong><?= $s['failed'] ?></strong> ·
        Bloqueados <strong><?= $s['blocked'] ?></strong> ·
        Cancelados <strong><?= $s['cancelled'] ?></strong>
    </div>
</div>

<?= Form::open() ?>
    <?= $this->formRenderPreview() ?>
    <?php if (!in_array($formModel->status, ['completed', 'cancelled'])): ?>
        <div class="form-buttons">
            <button type="button" class="btn btn-danger" data-request="onCancel"
                data-request-confirm="¿Cancelar los mensajes que aún están en cola y devolver sus créditos?">Cancelar lote</button>
        </div>
    <?php endif ?>
<?= Form::close() ?>
