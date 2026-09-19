<?php Block::put('breadcrumb') ?>
    <ul>
        <li><a href="<?= Backend::url('aero/sms/messages') ?>">Mensajes</a></li>
        <li><?= e($this->pageTitle) ?></li>
    </ul>
<?php Block::endPut() ?>

<?= Form::open() ?>
    <?= $this->formRenderPreview() ?>
    <?php if ($formModel->status === 'queued'): ?>
        <div class="form-buttons">
            <button type="button" class="btn btn-danger" data-request="onCancel"
                data-request-confirm="¿Cancelar este mensaje y devolver los créditos?">Cancelar mensaje</button>
        </div>
    <?php endif ?>
<?= Form::close() ?>
